<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\TipoDeFase;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Servicios\Torneos\Cuadros\EmparejadorSuizo;
use App\Servicios\Torneos\Cuadros\EscritorDeCuadros;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arranca un torneo: decide quién juega, lo siembra por ranking y arma la
 * primera fase.
 *
 *   1. Entran los equipos aprobados y, si el torneo usa check-in, que lo
 *      hicieron. Los aprobados que no lo hicieron quedan retirados.
 *   2. La siembra sale del ranking del juego: el mejor clasificado es la
 *      semilla 1. Los que no tienen historial van detrás, por orden de
 *      inscripción.
 *   3. Se crea la fase con sus partidos y el torneo pasa a «en curso».
 */
class InicioDeTorneo
{
    public function __construct(
        private readonly EscritorDeCuadros $escritor,
        private readonly EmparejadorSuizo $suizo,
        private readonly RankingDeEquipos $ranking,
    ) {}

    public function iniciar(Torneo $torneo): Torneo
    {
        return DB::transaction(function () use ($torneo): Torneo {
            /** @var Torneo $torneo */
            $torneo = Torneo::query()->whereKey($torneo->getKey())->lockForUpdate()->firstOrFail();

            if (! $torneo->estado->esperaInicio()) {
                throw ReglaDeNegocio::porqueSi('Solo se inicia un torneo publicado que todavía no empezó.');
            }

            $porRevisar = $torneo->inscripciones()->where('estado', EstadoInscripcion::PendienteRevision->value)->count();

            if ($porRevisar > 0) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'Hay %d %s por revisar. Apruébalas o recházalas antes de iniciar.',
                    $porRevisar,
                    $porRevisar === 1 ? 'inscripción' : 'inscripciones',
                ));
            }

            $aprobadas = $torneo->inscripciones()->aprobadas()->orderBy('id')->get();

            $participantes = $torneo->usaCheckIn()
                ? $aprobadas->filter(fn (Inscripcion $inscripcion): bool => $inscripcion->hizoCheckIn())->values()
                : $aprobadas;

            $this->verificarCantidad($torneo, $participantes->count());

            $semillas = $this->sembrar($torneo, $participantes);
            $this->retirarALosQueNoJuegan($torneo, $semillas);
            $this->armarPrimeraFase($torneo, $semillas);

            $torneo->forceFill(['estado' => EstadoTorneo::EnCurso])->save();

            return $torneo;
        });
    }

    private function verificarCantidad(Torneo $torneo, int $cantidad): void
    {
        $minimo = $torneo->formato->minimoDeEquipos();

        if ($torneo->formato === FormatoTorneo::GruposYEliminacion) {
            $minimo = max($minimo, 2 * (int) $torneo->cantidad_de_grupos);
        }

        if ($cantidad < $minimo) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                $torneo->usaCheckIn()
                    ? 'Hacen falta al menos %d equipos aprobados y con check-in; hay %d.'
                    : 'Hacen falta al menos %d equipos aprobados; hay %d.',
                $minimo,
                $cantidad,
            ));
        }

        $sala = $torneo->juego->equiposPorPartida();

        if ($torneo->formato->esBattleRoyale() && $cantidad > $sala) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                'En una sala de %s caben %d equipos y hay %d.',
                $torneo->juego->nombre(),
                $sala,
                $cantidad,
            ));
        }

        if ($torneo->formato === FormatoTorneo::GruposYEliminacion) {
            $porGrupo = intdiv($cantidad, (int) $torneo->cantidad_de_grupos);

            if ((int) $torneo->clasifican_por_grupo >= $porGrupo) {
                throw ReglaDeNegocio::porqueSi(sprintf(
                    'Con %d equipos, cada grupo queda de %d: no pueden clasificar %d por grupo.',
                    $cantidad,
                    $porGrupo,
                    $torneo->clasifican_por_grupo,
                ));
            }
        }
    }

    /**
     * @param  Collection<int, Inscripcion>  $participantes
     * @return list<int> Equipos en orden de semilla.
     */
    private function sembrar(Torneo $torneo, Collection $participantes): array
    {
        $puntajes = $this->ranking->puntajes($torneo->juego);
        $base = $torneo->juego->esBattleRoyale() ? 0 : RankingDeEquipos::ELO_INICIAL;

        $ordenadas = $participantes
            ->sortBy([
                fn (Inscripcion $una, Inscripcion $otra): int => ($puntajes[$otra->equipo_id] ?? $base) <=> ($puntajes[$una->equipo_id] ?? $base),
                fn (Inscripcion $una, Inscripcion $otra): int => $una->getKey() <=> $otra->getKey(),
            ])
            ->values();

        foreach ($ordenadas as $indice => $inscripcion) {
            $inscripcion->forceFill(['semilla' => $indice + 1])->save();
        }

        return $ordenadas->pluck('equipo_id')->all();
    }

    /**
     * @param  list<int>  $semillas
     */
    private function retirarALosQueNoJuegan(Torneo $torneo, array $semillas): void
    {
        $torneo->inscripciones()
            ->aprobadas()
            ->whereNotIn('equipo_id', $semillas)
            ->update([
                'estado' => EstadoInscripcion::Retirada->value,
                'notas_revision' => 'No hizo check-in.',
                'revisado_en' => now(),
            ]);

        $torneo->inscripciones()
            ->where('estado', EstadoInscripcion::PendientePago->value)
            ->update([
                'estado' => EstadoInscripcion::Retirada->value,
                'notas_revision' => 'El torneo empezó sin que se pagara la inscripción.',
                'revisado_en' => now(),
            ]);
    }

    /**
     * @param  list<int>  $semillas
     */
    private function armarPrimeraFase(Torneo $torneo, array $semillas): void
    {
        [$tipo, $nombre] = match ($torneo->formato) {
            FormatoTorneo::EliminacionSimple => [TipoDeFase::EliminacionSimple, 'Cuadro'],
            FormatoTorneo::EliminacionDoble => [TipoDeFase::EliminacionDoble, 'Cuadro'],
            FormatoTorneo::GruposYEliminacion => [TipoDeFase::Grupos, 'Fase de grupos'],
            FormatoTorneo::Suizo => [TipoDeFase::Suizo, 'Sistema suizo'],
            FormatoTorneo::BattleRoyale => [TipoDeFase::BattleRoyale, 'Partidas'],
        };

        $fase = (new Fase)->forceFill([
            'torneo_id' => $torneo->getKey(),
            'orden' => 1,
            'tipo' => $tipo,
            'nombre' => $nombre,
        ]);
        $fase->save();
        $fase->setRelation('torneo', $torneo);

        match ($torneo->formato) {
            FormatoTorneo::EliminacionSimple => $this->escritor->eliminacion($fase, $semillas, false),
            FormatoTorneo::EliminacionDoble => $this->escritor->eliminacion($fase, $semillas, true),
            FormatoTorneo::GruposYEliminacion => $this->escritor->grupos($fase, $semillas, (int) $torneo->cantidad_de_grupos),
            FormatoTorneo::Suizo => $this->primeraRondaSuiza($fase, $semillas),
            FormatoTorneo::BattleRoyale => $this->escritor->battleRoyale($fase, $torneo->partidas_battle_royale ?? 3),
        };
    }

    /**
     * @param  list<int>  $semillas
     */
    private function primeraRondaSuiza(Fase $fase, array $semillas): void
    {
        $emparejamiento = $this->suizo->emparejar($semillas);

        $this->escritor->rondaSuiza($fase, 1, $emparejamiento['cruces'], $emparejamiento['descansa']);
    }
}

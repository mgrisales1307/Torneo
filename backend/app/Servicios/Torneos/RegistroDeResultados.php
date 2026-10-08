<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\TipoDeFase;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Equipo;
use App\Models\Partido;
use App\Models\ReporteDeResultado;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Cómo llega un resultado a un partido.
 *
 * - Los capitanes reportan. Si los dos reportes coinciden, el partido se
 *   cierra solo; si no, queda en disputa para la organización. Es el flujo de
 *   las plataformas grandes: nadie tiene que esperar a un árbitro cuando los
 *   dos equipos están de acuerdo.
 * - La organización resuelve: fija el marcador de una disputa, o lo corrige.
 * - En battle royale, la organización carga los puestos y bajas de la sala.
 *
 * Todo pasa dentro de una transacción con el partido bloqueado: si los dos
 * capitanes reportan en el mismo instante, el segundo ve el reporte del
 * primero.
 */
class RegistroDeResultados
{
    public function __construct(
        private readonly AvanceDeCompetencia $avance,
        private readonly TablasDeClasificacion $tablas,
        private readonly RankingDeEquipos $ranking,
        private readonly AlmacenDeImagenes $imagenes,
    ) {}

    /**
     * @param  array{victorias_a: int, victorias_b: int, detalle?: array<int, array<string, mixed>>|null, comentario?: string|null}  $datos
     */
    public function reportar(Partido $partido, Equipo $equipo, Usuario $capitan, array $datos, ?UploadedFile $captura = null): Partido
    {
        return DB::transaction(function () use ($partido, $equipo, $capitan, $datos, $captura): Partido {
            $partido = $this->bloquear($partido);

            $this->verificarQueElTorneoEstaEnCurso($partido);

            if ($partido->estado === EstadoPartido::Pendiente) {
                throw ReglaDeNegocio::porqueSi('Todavía no se conoce el rival de este partido.');
            }

            if ($partido->estado === EstadoPartido::Finalizado) {
                throw ReglaDeNegocio::porqueSi(
                    'Este partido ya tiene resultado. Si hay un error, escríbele a la organización.'
                );
            }

            [$victoriasA, $victoriasB, $detalle] = $this->marcadorValido($partido, $datos);

            $reporte = ReporteDeResultado::query()
                ->where('partido_id', $partido->getKey())
                ->where('equipo_id', $equipo->getKey())
                ->first() ?? new ReporteDeResultado;

            $capturaAnterior = $reporte->captura_ruta;

            $reporte->forceFill([
                'partido_id' => $partido->getKey(),
                'equipo_id' => $equipo->getKey(),
                'usuario_id' => $capitan->getKey(),
                'victorias_a' => $victoriasA,
                'victorias_b' => $victoriasB,
                'detalle' => $detalle,
                'comentario' => $datos['comentario'] ?? null,
                'captura_ruta' => $captura !== null
                    ? $this->imagenes->guardar($captura, 'captura', $capturaAnterior)
                    : $capturaAnterior,
            ])->save();

            $delRival = $partido->reportes()->whereNot('equipo_id', $equipo->getKey())->first();

            if ($delRival === null) {
                $partido->forceFill(['estado' => EstadoPartido::Reportado])->save();

                return $partido;
            }

            if ($delRival->coincideCon($reporte)) {
                return $this->finalizar($partido, $victoriasA, $victoriasB, $detalle, null);
            }

            $partido->forceFill(['estado' => EstadoPartido::EnDisputa])->save();

            return $partido;
        });
    }

    /**
     * La organización fija el marcador: resuelve una disputa, carga un partido
     * que nadie reportó o corrige uno ya cerrado.
     *
     * @param  array{victorias_a: int, victorias_b: int, detalle?: array<int, array<string, mixed>>|null}  $datos
     */
    public function resolver(Partido $partido, Usuario $organizador, array $datos): Partido
    {
        return DB::transaction(function () use ($partido, $organizador, $datos): Partido {
            $partido = $this->bloquear($partido);

            if (! $partido->torneo->estado->haEmpezado()) {
                throw ReglaDeNegocio::porqueSi('Este torneo no está en juego.');
            }

            if ($partido->es_descanso || $partido->equipo_a_id === null || $partido->equipo_b_id === null) {
                throw ReglaDeNegocio::porqueSi('Este partido todavía no tiene a sus dos rivales.');
            }

            [$victoriasA, $victoriasB, $detalle] = $this->marcadorValido($partido, $datos);

            if ($partido->estaFinalizado()) {
                $this->avance->deshacer($partido);
            }

            return $this->finalizar($partido, $victoriasA, $victoriasB, $detalle, $organizador);
        });
    }

    /**
     * @param  list<array{equipo_id: int, posicion: int, bajas: int}>  $resultados
     */
    public function registrarBattleRoyale(Partido $partido, Usuario $organizador, array $resultados): Partido
    {
        return DB::transaction(function () use ($partido, $organizador, $resultados): Partido {
            $partido = $this->bloquear($partido);
            $torneo = $partido->torneo;

            if ($partido->fase->tipo !== TipoDeFase::BattleRoyale) {
                throw ReglaDeNegocio::porqueSi('Esta partida no es de battle royale.');
            }

            if (! $torneo->estado->haEmpezado()) {
                throw ReglaDeNegocio::porqueSi('Este torneo no está en juego.');
            }

            $participantes = $torneo->participantes()->pluck('equipo_id')->all();
            $this->verificarSala($resultados, $participantes);

            if ($partido->estaFinalizado()) {
                $this->avance->deshacer($partido);
            }

            $partido->resultadosBattleRoyale()->delete();
            $ahora = now();

            DB::table('resultados_battle_royale')->insert(array_map(fn (array $resultado): array => [
                'partido_id' => $partido->getKey(),
                'equipo_id' => $resultado['equipo_id'],
                'posicion' => $resultado['posicion'],
                'bajas' => $resultado['bajas'],
                'puntos' => $torneo->juego->puntosPorPosicion($resultado['posicion'])
                    + $resultado['bajas'] * $torneo->juego->puntosPorBaja(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $resultados));

            $ganador = collect($resultados)->firstWhere('posicion', 1);

            $partido->forceFill([
                'ganador_id' => $ganador['equipo_id'] ?? null,
                'estado' => EstadoPartido::Finalizado,
                'resuelto_por_id' => $organizador->getKey(),
                'finalizado_en' => now(),
            ])->save();

            $this->avance->alFinalizar($partido);
            $this->olvidarTablas($partido);

            return $partido->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $detalle
     */
    private function finalizar(Partido $partido, int $victoriasA, int $victoriasB, ?array $detalle, ?Usuario $organizador): Partido
    {
        $partido->forceFill([
            'victorias_a' => $victoriasA,
            'victorias_b' => $victoriasB,
            'ganador_id' => $victoriasA > $victoriasB ? $partido->equipo_a_id : $partido->equipo_b_id,
            'detalle' => $detalle,
            'estado' => EstadoPartido::Finalizado,
            'resuelto_por_id' => $organizador?->getKey(),
            'finalizado_en' => now(),
        ])->save();

        $this->avance->alFinalizar($partido);
        $this->olvidarTablas($partido);

        return $partido->refresh();
    }

    private function bloquear(Partido $partido): Partido
    {
        Torneo::query()->whereKey($partido->torneo_id)->lockForUpdate()->firstOrFail();
        /** @var Partido $bloqueado */
        $bloqueado = Partido::query()->whereKey($partido->getKey())->lockForUpdate()->firstOrFail();

        return $bloqueado;
    }

    private function verificarQueElTorneoEstaEnCurso(Partido $partido): void
    {
        if ($partido->torneo->estado !== EstadoTorneo::EnCurso) {
            throw ReglaDeNegocio::porqueSi('Este torneo no está en curso.');
        }
    }

    /**
     * Una serie al mejor de N la gana quien llega primero a (N / 2) + 1. Si
     * viene el detalle de cada mapa, tiene que cuadrar con el marcador.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: int, 1: int, 2: list<array{mapa: string|null, ganador: string}>|null}
     */
    private function marcadorValido(Partido $partido, array $datos): array
    {
        $victoriasA = (int) $datos['victorias_a'];
        $victoriasB = (int) $datos['victorias_b'];
        $necesarias = $partido->victoriasParaGanar();

        $ganador = max($victoriasA, $victoriasB);
        $perdedor = min($victoriasA, $victoriasB);

        if ($ganador !== $necesarias || $perdedor >= $necesarias) {
            throw ReglaDeNegocio::porqueSi(
                $partido->mejor_de === 1
                    ? 'Al mejor de 1 el marcador es 1-0 o 0-1.'
                    : sprintf(
                        'Al mejor de %d, el ganador suma %d y el perdedor menos de %d.',
                        $partido->mejor_de,
                        $necesarias,
                        $necesarias,
                    ),
                'victorias_a',
            );
        }

        $detalle = $datos['detalle'] ?? null;

        if (blank($detalle)) {
            return [$victoriasA, $victoriasB, null];
        }

        $detalle = array_values(array_map(fn (array $mapa): array => [
            'mapa' => filled($mapa['mapa'] ?? null) ? mb_substr(trim((string) $mapa['mapa']), 0, 40) : null,
            'ganador' => (string) ($mapa['ganador'] ?? ''),
        ], $detalle));

        $ganadosA = count(array_filter($detalle, fn (array $mapa): bool => $mapa['ganador'] === 'a'));
        $ganadosB = count(array_filter($detalle, fn (array $mapa): bool => $mapa['ganador'] === 'b'));

        if ($ganadosA !== $victoriasA || $ganadosB !== $victoriasB || $ganadosA + $ganadosB !== count($detalle)) {
            throw ReglaDeNegocio::porqueSi('El detalle de los mapas no coincide con el marcador.', 'detalle');
        }

        return [$victoriasA, $victoriasB, $detalle];
    }

    /**
     * La sala tiene que traer a todos los participantes, cada uno una vez, con
     * puestos del 1 en adelante sin repetir.
     *
     * @param  list<array{equipo_id: int, posicion: int, bajas: int}>  $resultados
     * @param  list<int>  $participantes
     */
    private function verificarSala(array $resultados, array $participantes): void
    {
        $equipos = array_column($resultados, 'equipo_id');
        $posiciones = array_column($resultados, 'posicion');

        if (array_diff($equipos, $participantes) !== []) {
            throw ReglaDeNegocio::porqueSi('Hay un equipo que no juega este torneo.', 'resultados');
        }

        if (count(array_unique($equipos)) !== count($participantes) || count($equipos) !== count($participantes)) {
            throw ReglaDeNegocio::porqueSi('Carga el puesto de cada uno de los equipos, una sola vez.', 'resultados');
        }

        sort($posiciones);

        if ($posiciones !== range(1, count($participantes))) {
            throw ReglaDeNegocio::porqueSi(
                sprintf('Los puestos van del 1 al %d, sin repetir.', count($participantes)),
                'resultados',
            );
        }
    }

    private function olvidarTablas(Partido $partido): void
    {
        foreach ($partido->torneo->fases()->get() as $fase) {
            $this->tablas->olvidar($fase);
        }

        $this->ranking->olvidar($partido->torneo->juego);
    }
}

<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoFase;
use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\TipoDeFase;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\Torneo;
use App\Servicios\Torneos\Cuadros\EmparejadorSuizo;
use App\Servicios\Torneos\Cuadros\EscritorDeCuadros;

/**
 * Lo que pasa en el torneo cuando un partido termina: el ganador sube en el
 * cuadro, el perdedor baja a la llave inferior, se arma la siguiente ronda
 * del suizo, se pasa de grupos a eliminatorias o se corona al campeón.
 *
 * Lo llama RegistroDeResultados, dentro de su transacción.
 */
class AvanceDeCompetencia
{
    public function __construct(
        private readonly EscritorDeCuadros $escritor,
        private readonly EmparejadorSuizo $suizo,
        private readonly TablasDeClasificacion $tablas,
    ) {}

    public function alFinalizar(Partido $partido): void
    {
        $fase = $partido->fase;

        match ($fase->tipo) {
            TipoDeFase::EliminacionSimple, TipoDeFase::EliminacionDoble => $this->avanzarEnElCuadro($partido, $fase),
            TipoDeFase::Grupos => $this->cerrarLosGruposSiTerminaron($fase),
            TipoDeFase::Suizo => $this->seguirConElSuizo($fase),
            TipoDeFase::BattleRoyale => $this->cerrarSiNoQuedanPartidas($fase),
        };
    }

    /**
     * Comprueba que corregir el resultado de un partido ya cerrado no deje el
     * torneo incoherente, y deshace lo que ese resultado había provocado. El
     * resultado nuevo se aplica después con `alFinalizar`.
     */
    public function deshacer(Partido $partido): void
    {
        $fase = $partido->fase;

        match ($fase->tipo) {
            TipoDeFase::EliminacionSimple, TipoDeFase::EliminacionDoble => $this->sacarDelCuadro($partido),
            TipoDeFase::Grupos => $this->verificarQueLosGruposSiguenAbiertos($fase),
            TipoDeFase::Suizo => $this->verificarQueEsLaUltimaRonda($partido, $fase),
            TipoDeFase::BattleRoyale => null,
        };

        if ($fase->estaFinalizada()) {
            $this->reabrir($fase);
        }
    }

    private function avanzarEnElCuadro(Partido $partido, Fase $fase): void
    {
        if ($partido->siguiente_ganador_id === null) {
            $this->cerrar($fase, $partido->ganador_id);

            return;
        }

        $this->colocar($partido->siguienteGanador, (string) $partido->siguiente_ganador_ranura, $partido->ganador_id);

        if ($partido->siguiente_perdedor_id !== null) {
            $this->colocar($partido->siguientePerdedor, (string) $partido->siguiente_perdedor_ranura, $partido->perdedorId());
        }
    }

    private function colocar(Partido $destino, string $ranura, ?int $equipoId): void
    {
        $destino->forceFill(["equipo_{$ranura}_id" => $equipoId]);

        $destino->estado = $destino->equipo_a_id !== null && $destino->equipo_b_id !== null
            ? EstadoPartido::PorJugar
            : EstadoPartido::Pendiente;

        $destino->save();
    }

    private function sacarDelCuadro(Partido $partido): void
    {
        $destinos = array_filter([
            [$partido->siguienteGanador, $partido->siguiente_ganador_ranura],
            [$partido->siguientePerdedor, $partido->siguiente_perdedor_ranura],
        ], fn (array $destino): bool => $destino[0] !== null);

        foreach ($destinos as [$destino]) {
            if ($destino->estado->yaSeJugo() || $destino->reportes()->exists()) {
                throw ReglaDeNegocio::porqueSi(
                    'El partido siguiente ya se jugó: corrige primero ese resultado.'
                );
            }
        }

        foreach ($destinos as [$destino, $ranura]) {
            $this->colocar($destino, (string) $ranura, null);
        }
    }

    private function verificarQueLosGruposSiguenAbiertos(Fase $fase): void
    {
        if ($fase->estaFinalizada()) {
            throw ReglaDeNegocio::porqueSi(
                'La fase de grupos ya cerró y las eliminatorias están armadas: este resultado ya no se puede cambiar.'
            );
        }
    }

    private function verificarQueEsLaUltimaRonda(Partido $partido, Fase $fase): void
    {
        $ultima = (int) $fase->partidos()->max('ronda');

        if ($partido->ronda < $ultima) {
            throw ReglaDeNegocio::porqueSi(
                'La ronda siguiente del suizo ya está emparejada: este resultado ya no se puede cambiar.'
            );
        }
    }

    private function cerrarLosGruposSiTerminaron(Fase $fase): void
    {
        if ($this->quedanPartidos($fase)) {
            return;
        }

        $this->tablas->olvidar($fase);
        $torneo = $fase->torneo;
        $clasifican = (int) $torneo->clasifican_por_grupo;
        $tablas = $this->tablas->calcular($fase);

        // Primero todos los primeros, luego todos los segundos. Con la siembra
        // clásica, el primero de un grupo cruza con el último clasificado de
        // otro, y dos del mismo grupo no se ven hasta lo más tarde posible.
        $semillas = [];

        for ($puesto = 0; $puesto < $clasifican; $puesto++) {
            foreach ($tablas as $filas) {
                if (isset($filas[$puesto])) {
                    $semillas[] = $filas[$puesto]['equipo']['id'];
                }
            }
        }

        $fase->forceFill(['estado' => EstadoFase::Finalizada])->save();

        $eliminatorias = (new Fase)->forceFill([
            'torneo_id' => $torneo->getKey(),
            'orden' => $fase->orden + 1,
            'tipo' => TipoDeFase::EliminacionSimple,
            'nombre' => 'Eliminatorias',
        ]);
        $eliminatorias->save();

        $this->escritor->eliminacion($eliminatorias, $semillas, false);
    }

    private function seguirConElSuizo(Fase $fase): void
    {
        $ronda = (int) $fase->partidos()->max('ronda');

        if ($fase->partidos()->where('ronda', $ronda)->whereNot('estado', EstadoPartido::Finalizado->value)->exists()) {
            return;
        }

        $this->tablas->olvidar($fase);
        $tabla = $this->tablas->calcular($fase)['general'];
        $torneo = $fase->torneo;
        $rondas = $torneo->rondas_suizo ?? $this->suizo->rondasRecomendadas(count($tabla));

        if ($ronda >= $rondas) {
            $this->cerrar($fase, $tabla[0]['equipo']['id'] ?? null);

            return;
        }

        $jugados = $fase->partidos()->get(['equipo_a_id', 'equipo_b_id', 'es_descanso']);
        $rivales = [];
        $descansaron = [];

        foreach ($jugados as $partido) {
            if ($partido->es_descanso) {
                $descansaron[] = $partido->equipo_a_id;

                continue;
            }

            $rivales[$partido->equipo_a_id][] = $partido->equipo_b_id;
            $rivales[$partido->equipo_b_id][] = $partido->equipo_a_id;
        }

        $emparejamiento = $this->suizo->emparejar(
            array_map(fn (array $fila): int => $fila['equipo']['id'], $tabla),
            $rivales,
            $descansaron,
        );

        $this->escritor->rondaSuiza($fase, $ronda + 1, $emparejamiento['cruces'], $emparejamiento['descansa']);
    }

    private function cerrarSiNoQuedanPartidas(Fase $fase): void
    {
        if ($this->quedanPartidos($fase)) {
            return;
        }

        $this->tablas->olvidar($fase);

        $this->cerrar($fase, $this->tablas->calcular($fase)['general'][0]['equipo']['id'] ?? null);
    }

    private function quedanPartidos(Fase $fase): bool
    {
        return $fase->partidos()->whereNot('estado', EstadoPartido::Finalizado->value)->exists();
    }

    /**
     * Cierra la última fase y con ella el torneo.
     */
    private function cerrar(Fase $fase, ?int $campeonId): void
    {
        $fase->forceFill(['estado' => EstadoFase::Finalizada])->save();

        $fase->torneo->forceFill([
            'estado' => EstadoTorneo::Finalizado,
            'campeon_id' => $campeonId,
            'finalizado_en' => now(),
        ])->save();
    }

    private function reabrir(Fase $fase): void
    {
        $fase->forceFill(['estado' => EstadoFase::EnCurso])->save();

        /** @var Torneo $torneo */
        $torneo = $fase->torneo;

        if ($torneo->estado === EstadoTorneo::Finalizado) {
            $torneo->forceFill([
                'estado' => EstadoTorneo::EnCurso,
                'campeon_id' => null,
                'finalizado_en' => null,
            ])->save();
        }
    }
}

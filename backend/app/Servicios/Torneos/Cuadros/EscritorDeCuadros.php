<?php

namespace App\Servicios\Torneos\Cuadros;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\LlaveDelCuadro;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Support\Carbon;

/**
 * Guarda en la base los cuadros que arman los constructores. Es la única
 * clase que crea partidos: así las reglas de qué estado le toca a un partido
 * recién creado, o qué serie se juega en la final, están en un solo sitio.
 */
class EscritorDeCuadros
{
    public function __construct(
        private readonly ConstructorDeEliminacion $eliminacion,
        private readonly RepartidorDeGrupos $grupos,
    ) {}

    /**
     * @param  list<int>  $semillas
     */
    public function eliminacion(Fase $fase, array $semillas, bool $doble): void
    {
        $torneo = $fase->torneo;
        $nodos = $this->eliminacion->construir($semillas, $doble);
        $ids = [];

        foreach ($nodos as $nodo) {
            $partido = $this->nuevoPartido($fase, $torneo, [
                'llave' => $nodo['llave'],
                'ronda' => $nodo['ronda'],
                'numero' => $nodo['numero'],
                'mejor_de' => $nodo['es_final'] ? $torneo->mejor_de_final : $torneo->mejor_de,
                'equipo_a_id' => $nodo['equipo_a'],
                'equipo_b_id' => $nodo['equipo_b'],
                'programado_para' => $this->esPrimeraRonda($nodo) ? $this->arranque($torneo) : null,
            ]);

            $ids[$nodo['clave']] = $partido->getKey();
        }

        // Los punteros se escriben después, cuando ya existen todos los ids.
        foreach ($nodos as $nodo) {
            $punteros = [];

            foreach (['ganador', 'perdedor'] as $lado) {
                if ($nodo["siguiente_$lado"] !== null) {
                    $punteros["siguiente_{$lado}_id"] = $ids[$nodo["siguiente_$lado"]['clave']];
                    $punteros["siguiente_{$lado}_ranura"] = $nodo["siguiente_$lado"]['ranura'];
                }
            }

            if ($punteros !== []) {
                Partido::query()->whereKey($ids[$nodo['clave']])->update($punteros);
            }
        }
    }

    /**
     * @param  list<int>  $semillas
     */
    public function grupos(Fase $fase, array $semillas, int $cantidadDeGrupos): void
    {
        $torneo = $fase->torneo;

        foreach ($this->grupos->repartir($semillas, $cantidadDeGrupos) as $letra => $equipos) {
            Inscripcion::query()
                ->where('torneo_id', $torneo->getKey())
                ->whereIn('equipo_id', $equipos)
                ->update(['grupo' => $letra]);

            foreach ($this->grupos->todosContraTodos($equipos) as $numero => $cruce) {
                $this->nuevoPartido($fase, $torneo, [
                    'grupo' => $letra,
                    'ronda' => $cruce['ronda'],
                    'numero' => $numero + 1,
                    'mejor_de' => $torneo->mejor_de,
                    'equipo_a_id' => $cruce['a'],
                    'equipo_b_id' => $cruce['b'],
                    'programado_para' => $this->arranque($torneo)->addDays($cruce['ronda'] - 1),
                ]);
            }
        }
    }

    /**
     * @param  list<array{a: int, b: int}>  $cruces
     */
    public function rondaSuiza(Fase $fase, int $ronda, array $cruces, ?int $descansa): void
    {
        $torneo = $fase->torneo;
        $fecha = $ronda === 1 ? $this->arranque($torneo) : null;

        foreach ($cruces as $indice => $cruce) {
            $this->nuevoPartido($fase, $torneo, [
                'ronda' => $ronda,
                'numero' => $indice + 1,
                'mejor_de' => $torneo->mejor_de,
                'equipo_a_id' => $cruce['a'],
                'equipo_b_id' => $cruce['b'],
                'programado_para' => $fecha,
            ]);
        }

        if ($descansa !== null) {
            // El descanso se guarda como un partido ya ganado: así cuenta en la
            // tabla y en el historial igual que cualquier victoria.
            $this->nuevoPartido($fase, $torneo, [
                'ronda' => $ronda,
                'numero' => count($cruces) + 1,
                'equipo_a_id' => $descansa,
                'ganador_id' => $descansa,
                'es_descanso' => true,
                'estado' => EstadoPartido::Finalizado,
                'finalizado_en' => now(),
            ]);
        }
    }

    public function battleRoyale(Fase $fase, int $partidas): void
    {
        $torneo = $fase->torneo;

        for ($numero = 1; $numero <= $partidas; $numero++) {
            $this->nuevoPartido($fase, $torneo, [
                'ronda' => $numero,
                'numero' => 1,
                'estado' => EstadoPartido::PorJugar,
                'programado_para' => $this->arranque($torneo)->addMinutes(45 * ($numero - 1)),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function nuevoPartido(Fase $fase, Torneo $torneo, array $atributos): Partido
    {
        $partido = new Partido;

        $partido->forceFill([
            'torneo_id' => $torneo->getKey(),
            'fase_id' => $fase->getKey(),
            'mejor_de' => 1,
            ...$atributos,
        ]);

        if (! isset($atributos['estado'])) {
            $partido->estado = $partido->equipo_a_id !== null && $partido->equipo_b_id !== null
                ? EstadoPartido::PorJugar
                : EstadoPartido::Pendiente;
        }

        $partido->save();

        return $partido;
    }

    /**
     * @param  array{llave: LlaveDelCuadro, ronda: int}  $nodo
     */
    private function esPrimeraRonda(array $nodo): bool
    {
        return $nodo['ronda'] === 1 && $nodo['llave'] === LlaveDelCuadro::Superior;
    }

    private function arranque(Torneo $torneo): Carbon
    {
        $inicio = $torneo->inicia_en;

        return $inicio !== null && $inicio->isFuture() ? $inicio->copy() : now()->addHour()->startOfHour();
    }
}

<?php

namespace Tests\Rasgos;

use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Torneos\InicioDeTorneo;
use App\Servicios\Torneos\RegistroDeResultados;

/**
 * Atajos para armar torneos en las pruebas: equipos completos, inscripciones
 * aprobadas y resultados cargados por la organización.
 */
trait ArmaTorneos
{
    private ?Usuario $organizador = null;

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function torneoConEquipos(int $equipos, FormatoTorneo $formato = FormatoTorneo::EliminacionSimple, array $atributos = [], Juego $juego = Juego::Valorant): Torneo
    {
        $torneo = Torneo::factory()->create([
            'juego' => $juego,
            'formato' => $formato,
            'cupo_maximo_equipos' => max(16, $equipos),
            ...$atributos,
        ]);

        for ($indice = 0; $indice < $equipos; $indice++) {
            $this->aprobar($torneo, Equipo::factory()->deJuego($juego)->completo()->create());
        }

        return $torneo;
    }

    protected function aprobar(Torneo $torneo, Equipo $equipo, bool $conCheckIn = false): Inscripcion
    {
        return Inscripcion::factory()->aprobada()->create([
            'torneo_id' => $torneo->getKey(),
            'equipo_id' => $equipo->getKey(),
            'check_in_en' => $conCheckIn ? now() : null,
        ]);
    }

    protected function iniciar(Torneo $torneo): Torneo
    {
        return app(InicioDeTorneo::class)->iniciar($torneo);
    }

    protected function organizador(): Usuario
    {
        return $this->organizador ??= Usuario::factory()->empleado()->create();
    }

    /**
     * La organización carga el resultado: gana el lado indicado.
     */
    protected function resolver(Partido $partido, string $gana = 'a'): Partido
    {
        $necesarias = $partido->victoriasParaGanar();

        return app(RegistroDeResultados::class)->resolver($partido->refresh(), $this->organizador(), [
            'victorias_a' => $gana === 'a' ? $necesarias : 0,
            'victorias_b' => $gana === 'b' ? $necesarias : 0,
        ]);
    }

    /**
     * Resuelve todos los partidos listos, ronda por ronda, hasta que no quede
     * ninguno. Gana siempre el lado A.
     */
    protected function jugarTodo(Torneo $torneo): void
    {
        while ($partido = $torneo->partidos()->where('estado', 'por_jugar')->orderBy('id')->first()) {
            $this->resolver($partido);
        }
    }
}

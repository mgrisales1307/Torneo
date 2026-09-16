<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoPartido;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Torneo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el calendario de un torneo de liga con el método del círculo (Berger):
 * los equipos se ponen en dos filas enfrentadas, una queda fija y la otra rota
 * una posición por jornada. Con n equipos salen n-1 jornadas en las que todos
 * juegan una vez contra todos, sin repetir ni descansar de más.
 *
 * Si el número de equipos es impar se agrega un hueco: el equipo que le toque
 * enfrentarlo, descansa esa jornada.
 */
class GeneradorDeCalendario
{
    public function __construct(private readonly CalculadoraDePosiciones $posiciones) {}

    /**
     * Genera y guarda los partidos del torneo. Devuelve cuántos creó.
     */
    public function generar(Torneo $torneo, bool $reiniciar = false): int
    {
        if (! $torneo->formato->esLiga()) {
            throw ReglaDeNegocio::porqueSi(
                'Por ahora el calendario automático solo está disponible para los formatos de liga.'
            );
        }

        if ($torneo->estado->estaCerrado()) {
            throw ReglaDeNegocio::porqueSi('Este torneo está cerrado: su calendario ya no se puede cambiar.');
        }

        $equipos = $torneo->inscripciones()
            ->aprobadas()
            ->orderBy('id')
            ->pluck('equipo_id')
            ->all();

        if (count($equipos) < 2) {
            throw ReglaDeNegocio::porqueSi(
                'Hacen falta al menos dos equipos con la inscripción aprobada para generar el calendario.'
            );
        }

        $this->verificarQuePuedeEscribir($torneo, $reiniciar);

        $partidos = $this->construirPartidos($torneo, $equipos);

        DB::transaction(function () use ($torneo, $partidos): void {
            $torneo->partidos()->delete();

            foreach (array_chunk($partidos, 100) as $lote) {
                DB::table('partidos')->insert($lote);
            }
        });

        $this->posiciones->olvidar($torneo);

        return count($partidos);
    }

    private function verificarQuePuedeEscribir(Torneo $torneo, bool $reiniciar): void
    {
        if (! $torneo->partidos()->exists()) {
            return;
        }

        if (! $reiniciar) {
            throw ReglaDeNegocio::porqueSi(
                'Este torneo ya tiene calendario. Vuelve a generarlo marcando que quieres reemplazarlo.'
            );
        }

        if ($torneo->partidos()->finalizados()->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'Ya hay partidos con resultado cargado: borrarlos perdería la tabla de posiciones. '
                .'Corrige los partidos uno por uno en lugar de volver a generar el calendario.'
            );
        }
    }

    /**
     * @param  array<int, int>  $equipos
     * @return array<int, array<string, mixed>>
     */
    private function construirPartidos(Torneo $torneo, array $equipos): array
    {
        $cruces = $this->crucesDeLaIda($equipos);

        if ($torneo->formato->tieneVuelta()) {
            $jornadasDeIda = max(array_column($cruces, 'jornada'));

            foreach ($cruces as $cruce) {
                $cruces[] = [
                    'jornada' => $cruce['jornada'] + $jornadasDeIda,
                    'local' => $cruce['visitante'],
                    'visitante' => $cruce['local'],
                    'es_vuelta' => true,
                ];
            }
        }

        $inicio = $this->fechaDeArranque($torneo);
        $ahora = now();
        $filas = [];

        foreach ($cruces as $indice => $cruce) {
            $filas[] = [
                'torneo_id' => $torneo->getKey(),
                'jornada' => $cruce['jornada'],
                'es_vuelta' => $cruce['es_vuelta'],
                'equipo_local_id' => $cruce['local'],
                'equipo_visitante_id' => $cruce['visitante'],
                'programado_para' => $inicio->copy()
                    ->addWeeks($cruce['jornada'] - 1)
                    ->addHours(2 * ($indice % 4)),
                'sede' => $torneo->sede,
                'estado' => EstadoPartido::Programado->value,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        return $filas;
    }

    /**
     * @param  array<int, int>  $equipos
     * @return array<int, array{jornada: int, local: int, visitante: int, es_vuelta: bool}>
     */
    private function crucesDeLaIda(array $equipos): array
    {
        $rueda = $equipos;

        // Con un número impar de equipos se agrega un hueco: a quien le toque
        // enfrentarlo, descansa esa jornada.
        if (count($rueda) % 2 !== 0) {
            $rueda[] = null;
        }

        $cantidad = count($rueda);
        $jornadas = $cantidad - 1;
        $mitad = intdiv($cantidad, 2);
        $cruces = [];

        for ($jornada = 1; $jornada <= $jornadas; $jornada++) {
            for ($posicion = 0; $posicion < $mitad; $posicion++) {
                $unEquipo = $rueda[$posicion];
                $otroEquipo = $rueda[$cantidad - 1 - $posicion];

                if ($unEquipo === null || $otroEquipo === null) {
                    continue;
                }

                // Alternar la localía reparte los partidos de casa y de visita
                // en vez de dejar a un equipo jugando siempre en el mismo lado.
                $intercambia = ($jornada + $posicion) % 2 === 0;

                $cruces[] = [
                    'jornada' => $jornada,
                    'local' => $intercambia ? $unEquipo : $otroEquipo,
                    'visitante' => $intercambia ? $otroEquipo : $unEquipo,
                    'es_vuelta' => false,
                ];
            }

            $rueda = $this->rotar($rueda);
        }

        return $cruces;
    }

    /**
     * Deja fijo el primer equipo y rota los demás una posición.
     *
     * @param  array<int, int|null>  $rueda
     * @return array<int, int|null>
     */
    private function rotar(array $rueda): array
    {
        $fijo = array_shift($rueda);
        $ultimo = array_pop($rueda);

        return array_merge([$fijo, $ultimo], $rueda);
    }

    /**
     * El calendario arranca en la fecha de inicio del torneo. Si no está
     * definida, la primera jornada queda la semana entrante a las 3 de la
     * tarde, y la organización la reprograma partido por partido.
     */
    private function fechaDeArranque(Torneo $torneo): Carbon
    {
        return $torneo->inicia_en?->copy() ?? now()->addWeek()->setTime(15, 0);
    }
}

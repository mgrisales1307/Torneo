<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\TipoEventoPartido;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registra el resultado de un partido: el marcador y los goles y tarjetas que
 * lo explican.
 *
 * El marcador que llega se compara con el que sale de los eventos. Si no
 * coinciden, no se guarda nada: es la forma de que la tabla de posiciones y la
 * de goleadores no puedan contar historias distintas del mismo partido.
 */
class RegistroDeResultados
{
    public function __construct(
        private readonly CalculadoraDePosiciones $posiciones,
        private readonly TablaDeGoleadores $goleadores,
    ) {}

    /**
     * @param  array{
     *     goles_local: int,
     *     goles_visitante: int,
     *     observaciones?: string|null,
     *     eventos?: array<int, array<string, mixed>>
     * }  $datos
     */
    public function registrar(Partido $partido, Usuario $usuario, array $datos): Partido
    {
        $this->verificarQueSePuedeCargar($partido);

        $eventos = $this->normalizarEventos($partido, $datos['eventos'] ?? []);
        $golesLocal = (int) $datos['goles_local'];
        $golesVisitante = (int) $datos['goles_visitante'];

        if ($eventos->isNotEmpty()) {
            $this->verificarQueElMarcadorCuadre($partido, $eventos, $golesLocal, $golesVisitante);
        }

        DB::transaction(function () use ($partido, $usuario, $eventos, $golesLocal, $golesVisitante, $datos): void {
            // Se reemplaza el detalle completo del partido: así corregir un
            // resultado es volver a cargarlo, sin eventos duplicados ni
            // huérfanos de la carga anterior.
            $partido->eventos()->delete();

            if ($eventos->isNotEmpty()) {
                $ahora = now();

                DB::table('eventos_partido')->insert(
                    $eventos->map(fn (array $evento): array => [
                        'partido_id' => $partido->getKey(),
                        'equipo_id' => $evento['equipo_id'],
                        'jugador_id' => $evento['jugador_id'],
                        'tipo' => $evento['tipo']->value,
                        'minuto' => $evento['minuto'],
                        'detalle' => $evento['detalle'],
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])->all()
                );
            }

            $partido->forceFill([
                'goles_local' => $golesLocal,
                'goles_visitante' => $golesVisitante,
                'estado' => EstadoPartido::Finalizado,
                'observaciones' => $datos['observaciones'] ?? $partido->observaciones,
                'registrado_por_id' => $usuario->getKey(),
                'finalizado_en' => now(),
            ])->save();
        });

        $this->olvidarTablas($partido);

        return $partido->refresh();
    }

    /**
     * Reprograma un partido o cambia su estado sin tocar el marcador.
     *
     * @param  array<string, mixed>  $datos
     */
    public function reprogramar(Partido $partido, array $datos): Partido
    {
        if ($partido->torneo->estado->estaCerrado()) {
            throw ReglaDeNegocio::porqueSi('Este torneo está cerrado: sus partidos ya no se pueden modificar.');
        }

        $partido->fill(array_intersect_key($datos, array_flip([
            'programado_para',
            'sede',
            'arbitro',
            'observaciones',
        ])));

        if (isset($datos['estado'])) {
            $nuevoEstado = $datos['estado'] instanceof EstadoPartido
                ? $datos['estado']
                : EstadoPartido::from((string) $datos['estado']);

            if ($nuevoEstado === EstadoPartido::Finalizado) {
                throw ReglaDeNegocio::porqueSi(
                    'Para dar un partido por finalizado hay que cargar su resultado.',
                    'estado',
                );
            }

            // Al dejar de estar finalizado, el partido pierde el marcador para
            // que la tabla de posiciones no siga contando un resultado que ya
            // no existe.
            if ($partido->estaFinalizado()) {
                $partido->forceFill([
                    'goles_local' => null,
                    'goles_visitante' => null,
                    'finalizado_en' => null,
                ]);
                $partido->eventos()->delete();
            }

            $partido->forceFill(['estado' => $nuevoEstado]);
        }

        $partido->save();

        $this->olvidarTablas($partido);

        return $partido->refresh();
    }

    private function verificarQueSePuedeCargar(Partido $partido): void
    {
        if ($partido->torneo->estado->estaCerrado()) {
            throw ReglaDeNegocio::porqueSi('Este torneo está cerrado: sus resultados ya no se pueden cambiar.');
        }

        if (in_array($partido->estado, [EstadoPartido::Cancelado, EstadoPartido::Aplazado], true)) {
            throw ReglaDeNegocio::porqueSi(sprintf(
                'Este partido está %s. Reprográmalo antes de cargar el resultado.',
                mb_strtolower($partido->estado->etiqueta()),
            ));
        }
    }

    /**
     * Comprueba que cada evento pertenezca al partido y devuelve la lista lista
     * para guardar.
     *
     * @param  array<int, array<string, mixed>>  $eventos
     * @return Collection<int, array{equipo_id: int, jugador_id: int, tipo: TipoEventoPartido, minuto: int|null, detalle: string|null}>
     */
    private function normalizarEventos(Partido $partido, array $eventos): Collection
    {
        if ($eventos === []) {
            return collect();
        }

        $equiposDelPartido = [$partido->equipo_local_id, $partido->equipo_visitante_id];

        $jugadores = Jugador::query()
            ->whereIn('equipo_id', $equiposDelPartido)
            ->get(['id', 'equipo_id'])
            ->keyBy('id');

        return collect($eventos)->values()->map(function (array $evento, int $indice) use ($equiposDelPartido, $jugadores): array {
            $numero = $indice + 1;
            $equipoId = (int) ($evento['equipo_id'] ?? 0);
            $jugadorId = (int) ($evento['jugador_id'] ?? 0);

            if (! in_array($equipoId, $equiposDelPartido, true)) {
                throw ReglaDeNegocio::porqueSi(
                    "El evento #$numero es de un equipo que no juega este partido.",
                    'eventos',
                );
            }

            $jugador = $jugadores->get($jugadorId);

            if ($jugador === null || $jugador->equipo_id !== $equipoId) {
                throw ReglaDeNegocio::porqueSi(
                    "El jugador del evento #$numero no pertenece al equipo indicado.",
                    'eventos',
                );
            }

            $tipo = TipoEventoPartido::tryFrom((string) ($evento['tipo'] ?? ''));

            if ($tipo === null) {
                throw ReglaDeNegocio::porqueSi("El evento #$numero tiene un tipo desconocido.", 'eventos');
            }

            return [
                'equipo_id' => $equipoId,
                'jugador_id' => $jugadorId,
                'tipo' => $tipo,
                'minuto' => isset($evento['minuto']) && $evento['minuto'] !== null ? (int) $evento['minuto'] : null,
                'detalle' => isset($evento['detalle']) && $evento['detalle'] !== null
                    ? mb_substr((string) $evento['detalle'], 0, 160)
                    : null,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $eventos
     */
    private function verificarQueElMarcadorCuadre(
        Partido $partido,
        Collection $eventos,
        int $golesLocal,
        int $golesVisitante,
    ): void {
        $calculados = [
            $partido->equipo_local_id => 0,
            $partido->equipo_visitante_id => 0,
        ];

        foreach ($eventos as $evento) {
            /** @var TipoEventoPartido $tipo */
            $tipo = $evento['tipo'];

            if ($tipo->sumaAlMarcador()) {
                $calculados[$evento['equipo_id']]++;

                continue;
            }

            // El autogol le suma al rival del equipo del jugador.
            if ($tipo === TipoEventoPartido::Autogol) {
                $rival = $evento['equipo_id'] === $partido->equipo_local_id
                    ? $partido->equipo_visitante_id
                    : $partido->equipo_local_id;

                $calculados[$rival]++;
            }
        }

        if ($calculados[$partido->equipo_local_id] === $golesLocal
            && $calculados[$partido->equipo_visitante_id] === $golesVisitante) {
            return;
        }

        throw ReglaDeNegocio::porqueSi(sprintf(
            'El marcador (%d-%d) no coincide con los goles registrados (%d-%d). Revisa los goles y los autogoles.',
            $golesLocal,
            $golesVisitante,
            $calculados[$partido->equipo_local_id],
            $calculados[$partido->equipo_visitante_id],
        ), 'eventos');
    }

    private function olvidarTablas(Partido $partido): void
    {
        $this->posiciones->olvidar($partido->torneo);
        $this->goleadores->olvidar($partido->torneo);
    }
}

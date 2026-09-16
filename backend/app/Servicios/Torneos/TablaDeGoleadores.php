<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\TipoEventoPartido;
use App\Models\EventoPartido;
use App\Models\Torneo;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Goleadores del torneo, contados directamente de los eventos de los partidos
 * finalizados. El autogol no cuenta para el jugador que lo hizo.
 */
class TablaDeGoleadores
{
    private const SEGUNDOS_EN_CACHE = 300;

    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function paraTorneo(Torneo $torneo, int $limite = 25): Collection
    {
        /** @var array<int, array<string, mixed>> $goleadores */
        $goleadores = Cache::remember(
            $this->claveDeCache($torneo, $limite),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => $this->calcular($torneo, $limite),
        );

        return collect($goleadores);
    }

    public function olvidar(Torneo $torneo): void
    {
        // Se limpian las variantes de límite que usa la aplicación.
        foreach ([10, 25, 50, 100] as $limite) {
            Cache::forget($this->claveDeCache($torneo, $limite));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function calcular(Torneo $torneo, int $limite): array
    {
        $tiposQueCuentan = collect(TipoEventoPartido::cases())
            ->filter(fn (TipoEventoPartido $tipo): bool => $tipo->cuentaComoGolDelJugador())
            ->map(fn (TipoEventoPartido $tipo): string => $tipo->value)
            ->all();

        return EventoPartido::query()
            ->toBase()
            ->join('partidos', 'partidos.id', '=', 'eventos_partido.partido_id')
            ->join('jugadores', 'jugadores.id', '=', 'eventos_partido.jugador_id')
            ->join('equipos', 'equipos.id', '=', 'jugadores.equipo_id')
            ->where('partidos.torneo_id', $torneo->getKey())
            ->whereIn('eventos_partido.tipo', $tiposQueCuentan)
            ->groupBy(
                'jugadores.id',
                'jugadores.nombres',
                'jugadores.apellidos',
                'jugadores.numero_camiseta',
                'jugadores.foto_ruta',
                'equipos.id',
                'equipos.nombre',
                'equipos.slug',
                'equipos.abreviatura',
                'equipos.escudo_ruta',
            )
            ->orderByDesc('goles')
            ->orderBy('jugadores.apellidos')
            ->limit($limite)
            ->get([
                'jugadores.id as jugador_id',
                'jugadores.nombres',
                'jugadores.apellidos',
                'jugadores.numero_camiseta',
                'jugadores.foto_ruta',
                'equipos.id as equipo_id',
                'equipos.nombre as equipo_nombre',
                'equipos.slug as equipo_slug',
                'equipos.abreviatura as equipo_abreviatura',
                'equipos.escudo_ruta',
                DB::raw('COUNT(*) as goles'),
                DB::raw(
                    "SUM(CASE WHEN eventos_partido.tipo = '".TipoEventoPartido::GolDePenal->value."' THEN 1 ELSE 0 END) as goles_de_penal"
                ),
            ])
            ->values()
            ->map(fn ($fila, int $indice): array => [
                'posicion' => $indice + 1,
                'jugador' => [
                    'id' => (int) $fila->jugador_id,
                    'nombre_completo' => trim($fila->nombres.' '.$fila->apellidos),
                    'numero_camiseta' => $fila->numero_camiseta !== null ? (int) $fila->numero_camiseta : null,
                    'foto_url' => $this->imagenes->url($fila->foto_ruta),
                ],
                'equipo' => [
                    'id' => (int) $fila->equipo_id,
                    'nombre' => $fila->equipo_nombre,
                    'slug' => $fila->equipo_slug,
                    'abreviatura' => $fila->equipo_abreviatura,
                    'escudo_url' => $this->imagenes->url($fila->escudo_ruta),
                ],
                'goles' => (int) $fila->goles,
                'goles_de_penal' => (int) $fila->goles_de_penal,
            ])
            ->all();
    }

    private function claveDeCache(Torneo $torneo, int $limite): string
    {
        return "torneo:{$torneo->getKey()}:goleadores:$limite";
    }
}

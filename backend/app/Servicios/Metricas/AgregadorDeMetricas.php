<?php

namespace App\Servicios\Metricas;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Frontera de privacidad entre la base operativa y Metabase.
 * Exporta conteos y percentiles, sin identificadores de sesión ni de personas.
 */
class AgregadorDeMetricas
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function construir(): array
    {
        $zona = config('metricas.zona_horaria');
        $inicio = CarbonImmutable::now($zona)->startOfDay()->subDays(config('metricas.retencion_dias') - 1)->utc();
        $trafico = $sesiones = $rendimiento = $actividad = [];

        foreach (DB::table('eventos_metrica')->where('created_at', '>=', $inicio)->orderBy('created_at')->cursor() as $evento) {
            $fecha = CarbonImmutable::parse($evento->created_at, 'UTC')->setTimezone($zona)->toDateString();
            if ($evento->tipo === 'pagina') {
                $clave = $fecha.'|'.$evento->pagina.'|'.$evento->dispositivo;
                $trafico[$clave] ??= ['fecha' => $fecha, 'pagina' => $evento->pagina, 'dispositivo' => $evento->dispositivo, 'visitas' => 0, 'sesiones' => []];
                $trafico[$clave]['visitas']++;
                $trafico[$clave]['sesiones'][$evento->sesion] = true;
                $sesiones[$fecha][$evento->sesion] = true;
            } else {
                $rendimiento[$fecha][$evento->tipo][] = (float) $evento->valor;
            }
        }

        $filasRendimiento = [];
        foreach ($rendimiento as $fecha => $metricas) {
            foreach ($metricas as $metrica => $valores) {
                sort($valores, SORT_NUMERIC);
                $filasRendimiento[] = ['fecha' => $fecha, 'metrica' => $metrica, 'p75' => $valores[(int) ceil(count($valores) * 0.75) - 1], 'muestras' => count($valores)];
            }
        }

        foreach (['registros' => ['usuarios', 'created_at'], 'inscripciones' => ['inscripciones', 'created_at'], 'pagos_virtuales_cop' => ['ordenes', 'pagada_en']] as $medida => [$tabla, $columna]) {
            $consulta = DB::table($tabla)->where($columna, '>=', $inicio);
            if ($tabla === 'usuarios') {
                $consulta->where('rol', 'jugador');
            }
            if ($tabla === 'ordenes') {
                $consulta->where('estado', 'pagada');
            }
            foreach ($consulta->select($columna)->when($tabla === 'ordenes', fn ($consulta) => $consulta->addSelect('monto_centavos'))->orderBy($columna)->cursor() as $fila) {
                $fecha = CarbonImmutable::parse($fila->{$columna}, 'UTC')->setTimezone($zona)->toDateString();
                $actividad[$fecha] ??= ['fecha' => $fecha, 'registros' => 0, 'inscripciones' => 0, 'pagos_virtuales_cop' => 0];
                $actividad[$fecha][$medida] += $tabla === 'ordenes' ? $fila->monto_centavos / 100 : 1;
            }
        }

        // Subconsultas por torneo evitan multiplicar cupos al unir inscripciones.
        $porJuego = DB::table('torneos')->select('juego', 'estado')
            ->selectRaw('COUNT(*) AS torneos, SUM(cupo_maximo_equipos) AS cupos')
            ->selectRaw('(SELECT COUNT(*) FROM inscripciones i JOIN torneos t ON t.id = i.torneo_id WHERE t.juego = torneos.juego AND t.estado = torneos.estado) AS inscripciones')
            ->selectRaw("(SELECT COUNT(*) FROM inscripciones i JOIN torneos t ON t.id = i.torneo_id WHERE t.juego = torneos.juego AND t.estado = torneos.estado AND i.estado = 'aprobada') AS confirmadas")
            ->groupBy('juego', 'estado')->get()->map(fn ($fila): array => (array) $fila)->all();

        return [
            'trafico_diario' => array_values(array_map(fn (array $fila): array => [...$fila, 'sesiones' => count($fila['sesiones'])], $trafico)),
            'sesiones_diarias' => array_map(fn (string $fecha, array $valores): array => ['fecha' => $fecha, 'sesiones' => count($valores)], array_keys($sesiones), array_values($sesiones)),
            'rendimiento_diario' => $filasRendimiento,
            'actividad_diaria' => array_values($actividad),
            'torneos_por_juego' => $porJuego,
            'inscripciones_por_estado' => DB::table('inscripciones')->select('estado')->selectRaw('COUNT(*) AS cantidad')->groupBy('estado')->get()->map(fn ($fila): array => (array) $fila)->all(),
            'resumen_plataforma' => [[
                'id' => 1,
                'jugadores' => DB::table('usuarios')->where('rol', 'jugador')->count(),
                'equipos' => DB::table('equipos')->where('es_individual', false)->count(),
                'torneos' => DB::table('torneos')->count(),
                'confirmadas' => DB::table('inscripciones')->where('estado', 'aprobada')->count(),
                'pagos_virtuales_cop' => DB::table('ordenes')->where('estado', 'pagada')->sum('monto_centavos') / 100,
                'actualizado_en' => now()->toIso8601String(),
            ]],
        ];
    }

    public function sincronizar(): void
    {
        $datos = DB::transaction(fn (): array => $this->construir());
        $almacen = DB::connection('metricas');
        $almacen->transaction(function () use ($datos, $almacen): void {
            $almacen->select('SELECT pg_advisory_xact_lock(74291)');
            foreach ($datos as $tabla => $filas) {
                $almacen->table($tabla)->delete();
                foreach (array_chunk($filas, 200) as $lote) {
                    $almacen->table($tabla)->insert($lote);
                }
            }
        });
        DB::table('configuracion_metricas')->updateOrInsert(['clave' => 'ultima_sincronizacion'], ['valor' => now()->toIso8601String()]);
        DB::table('eventos_metrica')->where('created_at', '<', now(config('metricas.zona_horaria'))->startOfDay()->subDays(config('metricas.retencion_dias') - 1)->utc())->delete();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Provisiona una instalación local real, conservando IDs al volver a ejecutar. */
class PrepararMetabase extends Command
{
    protected $signature = 'metricas:preparar';

    protected $description = 'Configura Metabase, su conexión de lectura y el panel NexZone';

    public function handle(): int
    {
        if (! config('metricas.habilitadas') || ! config('metricas.metabase.contrasena')) {
            $this->error('Falta el entorno privado de métricas. Consulta docs/metricas.md.');

            return self::FAILURE;
        }

        $cliente = Http::baseUrl(rtrim(config('metricas.metabase.url_interna'), '/').'/api')
            ->acceptJson()->connectTimeout(5)->timeout(60);
        $propiedades = $cliente->get('/session/properties')->throw()->json();
        if ($propiedades['setup-token'] ?? null) {
            $cliente->post('/setup', [
                'token' => $propiedades['setup-token'],
                'user' => ['email' => config('metricas.metabase.correo'), 'password' => config('metricas.metabase.contrasena'), 'first_name' => 'NexZone', 'last_name' => 'Administración'],
                'prefs' => ['site_name' => 'NexZone · Métricas', 'site_locale' => 'es', 'allow_tracking' => false],
            ])->throw();
        }

        $sesion = $cliente->post('/session', ['username' => config('metricas.metabase.correo'), 'password' => config('metricas.metabase.contrasena')])->throw()->json('id');
        $cliente = $cliente->withHeaders(['X-Metabase-Session' => $sesion]);
        try {
            $this->provisionar($cliente);
        } finally {
            $cliente->delete('/session');
        }
        $this->info('Panel de Metabase preparado. Abre Organización → Métricas.');

        return self::SUCCESS;
    }

    private function provisionar(PendingRequest $cliente): void
    {
        $bases = $cliente->get('/database')->throw()->json();
        $base = collect($bases['data'] ?? $bases)->firstWhere('name', 'NexZone · Agregados');
        if (! $base) {
            $base = $cliente->post('/database', [
                'name' => 'NexZone · Agregados', 'engine' => 'postgres',
                'details' => [
                    'host' => config('database.connections.metricas.host'),
                    'port' => (int) config('database.connections.metricas.port'),
                    'dbname' => config('database.connections.metricas.database'),
                    'user' => 'metricas_lector', 'password' => config('metricas.metabase.contrasena_lector'),
                    'ssl' => config('database.connections.metricas.sslmode') !== 'disable',
                ],
                'is_full_sync' => true,
            ])->throw()->json();
        }

        $colecciones = $cliente->get('/collection')->throw()->json();
        $coleccion = collect($colecciones['data'] ?? $colecciones)->firstWhere('name', 'NexZone · Métricas');
        $coleccion ??= $cliente->post('/collection', ['name' => 'NexZone · Métricas', 'description' => 'Panel administrado desde el repositorio Torneo. No contiene datos personales.'])->throw()->json();

        $paneles = $cliente->get('/dashboard')->throw()->json();
        $panel = collect($paneles['data'] ?? $paneles)->first(fn (array $panel): bool => $panel['name'] === 'NexZone · Métricas de la plataforma' && $panel['collection_id'] === $coleccion['id']);
        $panel ??= $cliente->post('/dashboard', ['name' => 'NexZone · Métricas de la plataforma', 'collection_id' => $coleccion['id'], 'description' => 'Tráfico con consentimiento, rendimiento de cargas y actividad de torneos. Calendario de Bogotá. Series de 30 días; inventarios actuales. Pagos exclusivamente virtuales.'])->throw()->json();

        $preguntas = $cliente->get('/card')->throw()->json();
        $preguntas = collect($preguntas['data'] ?? $preguntas);
        $panelActual = $cliente->get('/dashboard/'.$panel['id'])->throw()->json();
        $tarjetas = [];
        $fila = $columna = 0;
        foreach (config('panel_metricas') as $indice => $definicion) {
            $nombre = 'NZ · '.$definicion['nombre'];
            $existente = $preguntas->first(fn (array $pregunta): bool => $pregunta['name'] === $nombre && $pregunta['collection_id'] === $coleccion['id']);
            $visualizacion = isset($definicion['x']) ? ['graph.dimensions' => [$definicion['x']], 'graph.metrics' => $definicion['y']] : (object) [];
            $cuerpo = [
                'name' => $nombre, 'collection_id' => $coleccion['id'], 'display' => $definicion['tipo'],
                'dataset_query' => ['type' => 'native', 'database' => $base['id'], 'native' => ['query' => $definicion['sql'], 'template-tags' => (object) []]],
                'visualization_settings' => $visualizacion,
            ];
            $pregunta = $existente
                ? $cliente->put('/card/'.$existente['id'], $cuerpo)->throw()->json()
                : $cliente->post('/card', $cuerpo)->throw()->json();
            $tarjetaExistente = collect($panelActual['dashcards'])->firstWhere('card_id', $pregunta['id']);
            $tarjetas[] = [
                'id' => $tarjetaExistente['id'] ?? -($indice + 1), 'card_id' => $pregunta['id'],
                'row' => $fila, 'col' => $columna, 'size_x' => $definicion['ancho'], 'size_y' => $definicion['alto'],
                'parameter_mappings' => [],
                'visualization_settings' => ['card.title' => $definicion['titulo'] ?? $definicion['nombre']],
            ];
            $columna += $definicion['ancho'];
            if ($columna >= 24) {
                $columna = 0;
                $fila += $definicion['alto'];
            }
        }
        $cliente->put('/dashboard/'.$panel['id'], ['dashcards' => $tarjetas, 'enable_embedding' => true, 'embedding_params' => (object) []])->throw();
        DB::table('configuracion_metricas')->updateOrInsert(['clave' => 'metabase_panel'], ['valor' => (string) $panel['id']]);
    }
}

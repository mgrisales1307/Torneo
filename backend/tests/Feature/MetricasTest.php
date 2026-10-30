<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\Metricas\AgregadorDeMetricas;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MetricasTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['metricas.habilitadas' => true]);
    }

    /** @return array<string, mixed> */
    private function evento(array $cambios = []): array
    {
        return [...[
            'id' => (string) Str::uuid(), 'sesion' => (string) Str::uuid(),
            'consentimiento' => true, 'tipo' => 'pagina', 'pagina' => 'inicio', 'dispositivo' => 'movil',
        ], ...$cambios];
    }

    public function test_acepta_eventos_anonimos_y_no_duplica_reintentos(): void
    {
        $evento = $this->evento(['correo' => 'no-guardar@example.test', 'ruta' => '/jugadores/privado?secreto=1']);
        $this->postJson('/api/v1/metricas/eventos', $evento)->assertNoContent();
        $this->postJson('/api/v1/metricas/eventos', $evento)->assertNoContent();
        $this->assertDatabaseCount('eventos_metrica', 1);
        $fila = (array) DB::table('eventos_metrica')->first();
        $this->assertArrayNotHasKey('correo', $fila);
        $this->assertArrayNotHasKey('ruta', $fila);
        $this->assertNull($fila['valor']);
    }

    public function test_actualiza_una_muestra_sin_duplicarla_ni_permitir_cambiarla_desde_otra_sesion(): void
    {
        $evento = $this->evento(['tipo' => 'cls', 'valor' => 0.1]);
        $this->postJson('/api/v1/metricas/eventos', $evento)->assertNoContent();
        $this->postJson('/api/v1/metricas/eventos', [...$evento, 'valor' => 0.2])->assertNoContent();
        $this->postJson('/api/v1/metricas/eventos', [...$evento, 'sesion' => (string) Str::uuid(), 'valor' => 0.9])->assertNoContent();
        $this->assertDatabaseCount('eventos_metrica', 1);
        $this->assertDatabaseHas('eventos_metrica', ['id' => $evento['id'], 'valor' => 0.2]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function eventosInvalidos(): array
    {
        return [
            'sin permiso' => [['consentimiento' => false]],
            'url con identidad' => [['pagina' => '/jugadores/Jose']],
            'tipo libre' => [['tipo' => 'clic en correo']],
            'sesion invalida' => [['sesion' => 'correo@example.test']],
            'sin medida' => [['tipo' => 'lcp']],
            'medida negativa' => [['tipo' => 'inp', 'valor' => -1]],
            'cls fuera de limite' => [['tipo' => 'cls', 'valor' => 101]],
            'dispositivo libre' => [['dispositivo' => 'nombre del equipo']],
        ];
    }

    #[DataProvider('eventosInvalidos')]
    public function test_rechaza_entradas_fuera_del_contrato(array $cambios): void
    {
        $this->postJson('/api/v1/metricas/eventos', $this->evento($cambios))->assertUnprocessable();
        $this->assertDatabaseCount('eventos_metrica', 0);
    }

    public function test_respeta_desactivacion_y_senales_de_privacidad(): void
    {
        $this->withHeaders(['DNT' => '1'])->postJson('/api/v1/metricas/eventos', $this->evento())->assertNoContent();
        $this->flushHeaders();
        $this->withHeaders(['Sec-GPC' => '1'])->postJson('/api/v1/metricas/eventos', $this->evento())->assertNoContent();
        $this->flushHeaders();
        config(['metricas.habilitadas' => false]);
        $this->postJson('/api/v1/metricas/eventos', $this->evento())->assertNoContent();
        $this->getJson('/api/v1/metricas/configuracion')->assertExactJson(['habilitadas' => false]);
        $this->assertDatabaseCount('eventos_metrica', 0);
    }

    public function test_limita_la_ingesta(): void
    {
        for ($indice = 0; $indice < 30; $indice++) {
            $this->postJson('/api/v1/metricas/eventos', $this->evento())->assertNoContent();
        }
        $this->postJson('/api/v1/metricas/eventos', $this->evento())->assertTooManyRequests();
        $this->assertDatabaseCount('eventos_metrica', 30);
    }

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return ['admin' => ['admin', 200], 'empleado' => ['empleado', 200], 'jugador' => ['jugador', 403]];
    }

    #[DataProvider('roles')]
    public function test_solo_la_organizacion_recibe_el_panel(string $rol, int $estado): void
    {
        $this->actingAs(Usuario::factory()->create(['rol' => $rol]))->getJson('/api/v1/admin/metricas')->assertStatus($estado);
    }

    public function test_visitantes_no_reciben_tokens(): void
    {
        $this->getJson('/api/v1/admin/metricas')->assertUnauthorized();
    }

    public function test_firma_el_panel_fijo_con_caducidad_y_sin_exponer_la_clave(): void
    {
        $this->freezeTime();
        config(['metricas.metabase.url' => 'https://metricas.example.test', 'metricas.metabase.clave' => str_repeat('a', 64)]);
        DB::table('configuracion_metricas')->insert(['clave' => 'metabase_panel', 'valor' => '7']);
        $respuesta = $this->actingAs(Usuario::factory()->create(['rol' => 'admin']))
            ->getJson('/api/v1/admin/metricas?dashboard=99')->assertOk()->assertJsonPath('configurado', true);
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        [$cabecera, $carga, $firma] = explode('.', $respuesta->json('jwt'));
        $datos = json_decode(base64_decode(strtr($carga, '-_', '+/')), true);
        $this->assertSame(['dashboard' => 7], $datos['resource']);
        $this->assertSame(now()->addMinutes(10)->timestamp, $datos['exp']);
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', $cabecera.'.'.$carga, str_repeat('a', 64), true)), '+/', '-_'), '='), $firma);
        $this->assertStringNotContainsString(str_repeat('a', 64), $respuesta->getContent());
    }

    public function test_panel_sin_configurar_no_simula_datos(): void
    {
        $this->actingAs(Usuario::factory()->create(['rol' => 'admin']))->getJson('/api/v1/admin/metricas')->assertExactJson(['configurado' => false]);
    }

    public function test_desactivar_metricas_no_requiere_tablas_de_la_integracion(): void
    {
        config(['metricas.habilitadas' => false]);
        Schema::drop('configuracion_metricas');

        $this->actingAs(Usuario::factory()->create(['rol' => 'admin']))
            ->getJson('/api/v1/admin/metricas')->assertExactJson(['configurado' => false]);
    }

    public function test_agrega_por_dia_colombiano_deduplica_sesiones_y_calcula_p75(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29)->setTime(12, 0));
        $sesion = (string) Str::uuid();
        foreach (['inicio', 'torneos'] as $pagina) {
            DB::table('eventos_metrica')->insert([...array_diff_key($this->evento(['sesion' => $sesion, 'pagina' => $pagina]), ['consentimiento' => true]), 'valor' => null, 'created_at' => '2026-10-29 04:59:00']);
        }
        foreach ([100, 200, 300, 400] as $valor) {
            DB::table('eventos_metrica')->insert([...array_diff_key($this->evento(['tipo' => 'lcp', 'valor' => $valor]), ['consentimiento' => true]), 'created_at' => '2026-10-29 04:59:00']);
        }
        DB::table('eventos_metrica')->insert([...array_diff_key($this->evento(), ['consentimiento' => true]), 'valor' => null, 'created_at' => '2026-01-01 00:00:00']);
        $datos = app(AgregadorDeMetricas::class)->construir();
        $this->assertCount(2, $datos['trafico_diario']);
        $this->assertSame([['fecha' => '2026-10-28', 'sesiones' => 1]], $datos['sesiones_diarias']);
        $this->assertSame([['fecha' => '2026-10-28', 'metrica' => 'lcp', 'p75' => 300.0, 'muestras' => 4]], $datos['rendimiento_diario']);
        $this->assertStringNotContainsString($sesion, json_encode($datos));
    }
}

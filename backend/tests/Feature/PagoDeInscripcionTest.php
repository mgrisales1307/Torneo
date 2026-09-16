<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoOrden;
use App\Enumeraciones\EstadoPago;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Orden;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Pagos\PasarelaSimulada;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * El cobro de la inscripción, que es la parte del sistema donde un error
 * cuesta dinero de verdad.
 */
class PagoDeInscripcionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Usuario $dueno;

    private Inscripcion $inscripcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dueno = Usuario::factory()->dueno()->create();

        $this->inscripcion = Inscripcion::factory()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => Equipo::factory()->delDueno($this->dueno)->create()->getKey(),
            'monto_centavos' => 15_000_000,
        ]);
    }

    public function test_el_dueno_genera_la_orden_de_pago_de_su_inscripcion(): void
    {
        $respuesta = $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/inscripciones/{$this->inscripcion->id}/orden")
            ->assertCreated();

        $respuesta->assertJsonPath('data.monto_centavos', 15_000_000)
            ->assertJsonPath('data.monto_formateado', '$ 150.000')
            ->assertJsonPath('data.estado.valor', EstadoOrden::Pendiente->value)
            ->assertJsonPath('cobro.pasarela', 'simulada');

        $referencia = $respuesta->json('data.referencia');

        // La referencia es aleatoria, no un número consecutivo que se pueda
        // adivinar para llegar a la orden de otro equipo.
        $this->assertMatchesRegularExpression('/^JZ-[A-Z0-9]{14}$/', $referencia);
        $this->assertSame("/panel/pagos/$referencia", $respuesta->json('cobro.url'));
    }

    public function test_pedir_la_orden_dos_veces_no_crea_dos_cobros(): void
    {
        $primera = $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/inscripciones/{$this->inscripcion->id}/orden")
            ->assertCreated()
            ->json('data.referencia');

        $segunda = $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/inscripciones/{$this->inscripcion->id}/orden")
            ->assertCreated()
            ->json('data.referencia');

        $this->assertSame($primera, $segunda);
        $this->assertDatabaseCount('ordenes', 1);
    }

    public function test_una_orden_vencida_se_marca_expirada_y_se_genera_una_nueva(): void
    {
        $vencida = Orden::factory()->deLaInscripcion($this->inscripcion)->expirada()->create();

        $nueva = $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/inscripciones/{$this->inscripcion->id}/orden")
            ->assertCreated()
            ->json('data.referencia');

        $this->assertSame(EstadoOrden::Expirada, $vencida->refresh()->estado);
        $this->assertNotSame($vencida->referencia, $nueva);
    }

    public function test_el_pago_aprobado_deja_la_inscripcion_en_revision(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/ordenes/{$orden->referencia}/pagar-simulado", ['resultado' => 'aprobado'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoOrden::Pagada->value);

        $this->assertSame(EstadoInscripcion::PendienteRevision, $this->inscripcion->refresh()->estado);
        $this->assertNotNull($this->inscripcion->pagada_en);
        $this->assertDatabaseHas('pagos', [
            'orden_id' => $orden->id,
            'estado' => EstadoPago::Aprobado->value,
            'monto_centavos' => 15_000_000,
        ]);
    }

    public function test_el_pago_rechazado_deja_la_inscripcion_sin_pagar(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/ordenes/{$orden->referencia}/pagar-simulado", ['resultado' => 'rechazado'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoOrden::Fallida->value);

        $this->assertSame(EstadoInscripcion::PendientePago, $this->inscripcion->refresh()->estado);
    }

    public function test_nadie_puede_pagar_ni_mirar_la_orden_de_otro(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();
        $intruso = Usuario::factory()->dueno()->create();

        $this->actingAs($intruso)->getJson("/api/v1/panel/ordenes/{$orden->referencia}")->assertForbidden();

        $this->actingAs($intruso)
            ->postJson("/api/v1/panel/ordenes/{$orden->referencia}/pagar-simulado", ['resultado' => 'aprobado'])
            ->assertForbidden();

        $this->assertSame(EstadoOrden::Pendiente, $orden->refresh()->estado);
        $this->assertSame(EstadoInscripcion::PendientePago, $this->inscripcion->refresh()->estado);
    }

    public function test_una_orden_ya_pagada_no_se_vuelve_a_cobrar(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->pagada()->create();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/ordenes/{$orden->referencia}/pagar-simulado", ['resultado' => 'aprobado'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_la_notificacion_sin_firma_valida_se_rechaza(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $cuerpo = $this->cuerpoDeNotificacion($orden);

        // Sin cabeceras de firma.
        $this->postJson('/api/v1/pagos/notificaciones/simulada', $cuerpo)->assertForbidden();

        // Con una firma inventada.
        $this->withHeaders([
            PasarelaSimulada::CABECERA_FIRMA => str_repeat('a', 64),
            PasarelaSimulada::CABECERA_MARCA_DE_TIEMPO => (string) now()->getTimestamp(),
        ])->postJson('/api/v1/pagos/notificaciones/simulada', $cuerpo)->assertForbidden();

        $this->assertSame(EstadoOrden::Pendiente, $orden->refresh()->estado);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_la_notificacion_firmada_fuera_de_tiempo_se_rechaza(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $respuesta = $this->enviarNotificacion($orden, marcaDeTiempo: now()->subHour()->getTimestamp());

        $respuesta->assertForbidden();
        $this->assertSame(EstadoOrden::Pendiente, $orden->refresh()->estado);
    }

    public function test_la_misma_notificacion_repetida_no_cobra_dos_veces(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();
        $referenciaExterna = 'SIM-REPETIDA-1';

        $this->enviarNotificacion($orden, referenciaExterna: $referenciaExterna)->assertOk();
        $this->enviarNotificacion($orden, referenciaExterna: $referenciaExterna)->assertOk();

        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame(EstadoOrden::Pagada, $orden->refresh()->estado);
        $this->assertSame(EstadoInscripcion::PendienteRevision, $this->inscripcion->refresh()->estado);
    }

    public function test_una_notificacion_con_un_monto_distinto_no_da_por_pagada_la_orden(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $this->enviarNotificacion($orden, monto: 100)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El monto notificado no coincide con el de la orden.');

        $this->assertSame(EstadoOrden::Pendiente, $orden->refresh()->estado);
        $this->assertSame(EstadoInscripcion::PendientePago, $this->inscripcion->refresh()->estado);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_una_notificacion_de_una_orden_inexistente_devuelve_404(): void
    {
        $orden = Orden::factory()->deLaInscripcion($this->inscripcion)->create();

        $this->enviarNotificacion($orden, referencia: 'JZ-NOEXISTE00000')->assertNotFound();
    }

    public function test_no_se_genera_orden_de_una_inscripcion_ya_pagada(): void
    {
        $this->inscripcion->forceFill(['estado' => EstadoInscripcion::Aprobada])->save();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/inscripciones/{$this->inscripcion->id}/orden")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta inscripción ya está pagada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpoDeNotificacion(Orden $orden, ?int $monto = null, ?string $referenciaExterna = null, ?string $referencia = null): array
    {
        return [
            'referencia' => $referencia ?? $orden->referencia,
            'referencia_externa' => $referenciaExterna ?? 'SIM-'.Str::upper(Str::random(10)),
            'estado' => EstadoPago::Aprobado->value,
            'monto_centavos' => $monto ?? $orden->monto_centavos,
            'moneda' => $orden->moneda,
            'metodo' => 'tarjeta_de_prueba',
        ];
    }

    /**
     * Envía una notificación firmada como lo haría la pasarela.
     */
    private function enviarNotificacion(
        Orden $orden,
        ?int $monto = null,
        ?string $referenciaExterna = null,
        ?string $referencia = null,
        ?int $marcaDeTiempo = null,
    ): TestResponse {
        $cuerpo = $this->cuerpoDeNotificacion($orden, $monto, $referenciaExterna, $referencia);
        $json = json_encode($cuerpo, JSON_THROW_ON_ERROR);
        $sello = (string) ($marcaDeTiempo ?? now()->getTimestamp());

        return $this->call(
            method: 'POST',
            uri: '/api/v1/pagos/notificaciones/simulada',
            server: $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                PasarelaSimulada::CABECERA_FIRMA => app(PasarelaSimulada::class)->firmar($sello, $json),
                PasarelaSimulada::CABECERA_MARCA_DE_TIEMPO => $sello,
            ]),
            content: $json,
        );
    }
}

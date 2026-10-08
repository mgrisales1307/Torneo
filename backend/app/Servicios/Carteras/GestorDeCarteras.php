<?php

namespace App\Servicios\Carteras;

use App\Excepciones\ReglaDeNegocio;
use Illuminate\Support\Facades\DB;

class GestorDeCarteras
{
    /** El movimiento y el saldo se escriben juntos; la referencia evita duplicados. */
    public function mover(int $usuarioId, string $moneda, int $monto, string $tipo, string $descripcion, string $referencia, ?int $torneoId = null, ?int $inscripcionId = null): void
    {
        DB::transaction(function () use ($usuarioId, $moneda, $monto, $tipo, $descripcion, $referencia, $torneoId, $inscripcionId): void {
            DB::table('carteras')->insertOrIgnore(['usuario_id' => $usuarioId, 'moneda' => $moneda, 'saldo_centavos' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $cartera = DB::table('carteras')->where('usuario_id', $usuarioId)->where('moneda', $moneda)->lockForUpdate()->first();
            if (DB::table('movimientos_cartera')->where('referencia', $referencia)->exists()) {
                return;
            }
            $nuevoSaldo = (int) $cartera->saldo_centavos + $monto;
            if ($nuevoSaldo < 0) {
                throw ReglaDeNegocio::porqueSi($tipo === 'ajuste_premio'
                    ? 'El premio anterior ya se utilizó o retiró. No se puede cambiar el beneficiario sin recuperar ese saldo.'
                    : 'No tienes saldo suficiente en la cartera. Puedes usar el pago simulado sin dinero real.');
            }
            DB::table('carteras')->where('id', $cartera->id)->update(['saldo_centavos' => $nuevoSaldo, 'updated_at' => now()]);
            DB::table('movimientos_cartera')->insert([
                'cartera_id' => $cartera->id, 'torneo_id' => $torneoId, 'inscripcion_id' => $inscripcionId,
                'referencia' => $referencia, 'tipo' => $tipo, 'descripcion' => $descripcion,
                'monto_centavos' => $monto, 'saldo_centavos' => $nuevoSaldo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function saldo(int $usuarioId, string $moneda = 'COP'): int
    {
        return (int) DB::table('carteras')->where('usuario_id', $usuarioId)->where('moneda', $moneda)->value('saldo_centavos');
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Servicios\Carteras\GestorDeCarteras;
use App\Soporte\Dinero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CarteraController extends Controller
{
    public function __construct(private readonly GestorDeCarteras $carteras) {}

    public function show(Request $peticion): JsonResponse
    {
        $usuarioId = $peticion->user()->getKey();
        $saldo = $this->carteras->saldo($usuarioId);
        $movimientos = DB::table('movimientos_cartera')->join('carteras', 'carteras.id', '=', 'movimientos_cartera.cartera_id')
            ->where('carteras.usuario_id', $usuarioId)->where('carteras.moneda', 'COP')
            ->orderByDesc('movimientos_cartera.id')->paginate(20, ['movimientos_cartera.*']);
        $movimientos->through(function (object $movimiento): object {
            $movimiento->created_at = Carbon::parse($movimiento->created_at, config('app.timezone'))->toIso8601String();
            $movimiento->updated_at = Carbon::parse($movimiento->updated_at, config('app.timezone'))->toIso8601String();

            return $movimiento;
        });

        return response()->json(['saldo_centavos' => $saldo, 'saldo_formateado' => Dinero::formatear($saldo, 'COP'), 'moneda' => 'COP', 'simulada' => true, 'movimientos' => $movimientos]);
    }

    public function retirar(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate(['monto_centavos' => ['required', 'integer', 'min:100', 'max:100000000000'], 'referencia' => ['required', 'uuid']]);
        $this->carteras->mover($peticion->user()->getKey(), 'COP', (int) $datos['monto_centavos'] * -1, 'retiro',
            'Retiro simulado completado', 'retiro:'.$peticion->user()->getKey().':'.$datos['referencia']);

        return response()->json(['message' => 'Retiro simulado completado. No se realizó ninguna transferencia real.']);
    }
}

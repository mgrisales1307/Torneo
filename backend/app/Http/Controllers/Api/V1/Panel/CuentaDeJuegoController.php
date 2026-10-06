<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enumeraciones\Juego;
use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarCuentaDeJuegoRequest;
use App\Http\Resources\CuentaDeJuegoResource;
use App\Models\CuentaDeJuego;
use App\Models\Equipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las cuentas de juego del jugador: su Riot ID, su Steam ID, etc.
 */
class CuentaDeJuegoController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        return response()->json([
            'data' => CuentaDeJuegoResource::collection($peticion->user()->cuentasDeJuego()->get()),
        ]);
    }

    public function update(GuardarCuentaDeJuegoRequest $peticion, Juego $juego): JsonResponse
    {
        $usuario = $peticion->user();

        // Con un torneo en curso no se cambia la cuenta: es lo que la
        // organización ya verificó.
        if ($this->compiteEn($peticion, $juego)) {
            throw ReglaDeNegocio::porqueSi(
                "Estás jugando un torneo de {$juego->nombre()}: tu cuenta no cambia hasta que termine."
            );
        }

        $cuenta = $usuario->cuentasDeJuego()->where('juego', $juego->value)->first() ?? new CuentaDeJuego;
        $cuenta->forceFill([
            'usuario_id' => $usuario->getKey(),
            'juego' => $juego,
            'identificador' => $peticion->validated('identificador'),
        ])->save();

        return response()->json([
            'message' => "{$juego->nombreDeCuenta()} guardado.",
            'data' => new CuentaDeJuegoResource($cuenta),
        ]);
    }

    public function destroy(Request $peticion, Juego $juego): JsonResponse
    {
        if ($this->compiteEn($peticion, $juego)) {
            throw ReglaDeNegocio::porqueSi(
                "Estás jugando un torneo de {$juego->nombre()}: tu cuenta no se puede quitar hasta que termine."
            );
        }

        $peticion->user()->cuentasDeJuego()->where('juego', $juego->value)->delete();

        return response()->json(['message' => "{$juego->nombreDeCuenta()} desvinculado."]);
    }

    private function compiteEn(Request $peticion, Juego $juego): bool
    {
        return Equipo::query()
            ->delMiembro($peticion->user())
            ->where('juego', $juego->value)
            ->get()
            ->contains(fn (Equipo $equipo): bool => $equipo->estaCompitiendo());
    }
}

<?php

namespace App\Http\Resources;

use App\Models\CuentaDeJuego;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CuentaDeJuego
 */
class CuentaDeJuegoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'juego' => $this->juego->value,
            'juego_nombre' => $this->juego->nombre(),
            'tipo' => $this->juego->nombreDeCuenta(),
            'identificador' => $this->identificador,
        ];
    }
}

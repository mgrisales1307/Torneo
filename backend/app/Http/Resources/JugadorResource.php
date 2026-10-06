<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El perfil público de un jugador: apodo, cuentas de juego y equipos. Ni el
 * nombre real ni el correo salen de aquí.
 *
 * @mixin Usuario
 */
class JugadorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'apodo' => $this->apodo,
            'cuentas' => CuentaDeJuegoResource::collection($this->whenLoaded('cuentasDeJuego')),
            'equipos' => $this->whenLoaded('equipos', fn () => $this->equipos->map(fn ($equipo): array => [
                ...$equipo->resumen(),
                'juego' => ['valor' => $equipo->juego->value, 'nombre' => $equipo->juego->nombre()],
                'es_capitan' => $equipo->capitan_id === $this->id,
                'es_suplente' => (bool) $equipo->pivot->es_suplente,
            ])),
            'desde' => $this->created_at?->toIso8601String(),
        ];
    }
}

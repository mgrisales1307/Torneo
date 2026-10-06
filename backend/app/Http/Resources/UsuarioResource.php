<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La cuenta tal como la ve su dueño o la organización. Lleva el correo y el
 * teléfono, así que nunca se usa en las páginas públicas: para eso está
 * JugadorResource.
 *
 * @mixin Usuario
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'apodo' => $this->apodo,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'rol' => [
                'valor' => $this->rol->value,
                'etiqueta' => $this->rol->etiqueta(),
            ],
            'es_staff' => $this->resource->perteneceAlStaff(),
            'cuentas' => CuentaDeJuegoResource::collection($this->whenLoaded('cuentasDeJuego')),
            'equipos_count' => $this->whenCounted('equipos'),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

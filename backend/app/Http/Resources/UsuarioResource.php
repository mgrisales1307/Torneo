<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'rol' => [
                'valor' => $this->rol->value,
                'etiqueta' => $this->rol->etiqueta(),
            ],
            'equipos_count' => $this->whenCounted('equipos'),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

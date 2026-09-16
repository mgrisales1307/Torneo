<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Ficha completa, con los datos personales. Solo la usan los endpoints del
 * dueño del equipo y de la organización.
 */
class JugadorPrivadoResource extends JugadorResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'documento_identidad' => $this->documento_identidad,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
        ]);
    }
}

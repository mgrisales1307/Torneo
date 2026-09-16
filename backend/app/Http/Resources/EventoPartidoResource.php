<?php

namespace App\Http\Resources;

use App\Models\EventoPartido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventoPartido
 */
class EventoPartidoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => [
                'valor' => $this->tipo->value,
                'etiqueta' => $this->tipo->etiqueta(),
            ],
            'minuto' => $this->minuto,
            'detalle' => $this->detalle,
            'equipo_id' => $this->equipo_id,
            'jugador_id' => $this->jugador_id,
            'jugador' => new JugadorResource($this->whenLoaded('jugador')),
        ];
    }
}

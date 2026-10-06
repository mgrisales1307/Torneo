<?php

namespace App\Http\Resources;

use App\Models\Fase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fase con sus partidos. La tabla, si la tiene, la agrega el controlador:
 * sale de TablasDeClasificacion y no de la fila de la fase.
 *
 * @mixin Fase
 */
class FaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'nombre' => $this->nombre,
            'tipo' => [
                'valor' => $this->tipo->value,
                'etiqueta' => $this->tipo->etiqueta(),
                'es_eliminacion' => $this->tipo->esEliminacion(),
            ],
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'partidos' => PartidoResource::collection($this->whenLoaded('partidos')),
        ];
    }
}

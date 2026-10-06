<?php

namespace App\Http\Resources;

use App\Models\ReporteDeResultado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReporteDeResultado
 */
class ReporteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'equipo_id' => $this->equipo_id,
            'equipo' => $this->whenLoaded('equipo', fn () => $this->equipo->nombre),
            'reportado_por' => $this->whenLoaded('usuario', fn () => $this->usuario->apodo),
            'victorias_a' => $this->victorias_a,
            'victorias_b' => $this->victorias_b,
            'detalle' => $this->detalle,
            'comentario' => $this->comentario,
            'captura_url' => $this->urlDeImagen($this->captura_ruta),
            'reportado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Orden;
use App\Soporte\Dinero;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Orden
 */
class OrdenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'referencia' => $this->referencia,
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'monto_centavos' => $this->monto_centavos,
            'monto_formateado' => Dinero::formatear($this->monto_centavos, $this->moneda),
            'moneda' => $this->moneda,
            'pasarela' => $this->pasarela,
            'admite_pago' => $this->admitePago(),
            'expira_en' => $this->expira_en?->toIso8601String(),
            'pagada_en' => $this->pagada_en?->toIso8601String(),
            'creado_en' => $this->created_at?->toIso8601String(),
            'inscripcion' => new InscripcionResource($this->whenLoaded('inscripcion')),
        ];
    }
}

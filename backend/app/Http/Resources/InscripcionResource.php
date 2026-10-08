<?php

namespace App\Http\Resources;

use App\Models\Inscripcion;
use App\Soporte\Dinero;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inscripcion
 */
class InscripcionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'puedo_pagar' => $request->user()?->can('pagar', $this->resource) ?? false,
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'monto_centavos' => $this->monto_centavos,
            'monto_formateado' => $this->monto_centavos === 0
                ? 'Gratis'
                : Dinero::formatear($this->monto_centavos, $this->moneda),
            'moneda' => $this->moneda,
            'espera_pago' => $this->esperaPago(),
            'check_in_en' => $this->check_in_en?->toIso8601String(),
            'semilla' => $this->semilla,
            'grupo' => $this->grupo,
            'notas_revision' => $this->notas_revision,
            'pagada_en' => $this->pagada_en?->toIso8601String(),
            'revisado_en' => $this->revisado_en?->toIso8601String(),
            'creado_en' => $this->created_at?->toIso8601String(),

            'torneo' => new TorneoResource($this->whenLoaded('torneo')),
            'equipo' => new EquipoResource($this->whenLoaded('equipo')),
            'revisor' => $this->whenLoaded('revisor', fn () => ['apodo' => $this->revisor?->apodo]),
            'ordenes' => OrdenResource::collection($this->whenLoaded('ordenes')),
        ];
    }
}

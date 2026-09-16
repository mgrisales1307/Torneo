<?php

namespace App\Http\Resources;

use App\Models\Partido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partido
 */
class PartidoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'torneo_id' => $this->torneo_id,
            'jornada' => $this->jornada,
            'es_vuelta' => $this->es_vuelta,
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'programado_para' => $this->programado_para?->toIso8601String(),
            'sede' => $this->sede,
            'arbitro' => $this->arbitro,
            'observaciones' => $this->observaciones,

            'marcador' => $this->tieneMarcador() ? [
                'local' => $this->goles_local,
                'visitante' => $this->goles_visitante,
            ] : null,

            'equipo_local' => new EquipoResumenResource($this->whenLoaded('equipoLocal')),
            'equipo_visitante' => new EquipoResumenResource($this->whenLoaded('equipoVisitante')),
            'torneo' => new TorneoResource($this->whenLoaded('torneo')),
            'eventos' => EventoPartidoResource::collection($this->whenLoaded('eventos')),
        ];
    }
}

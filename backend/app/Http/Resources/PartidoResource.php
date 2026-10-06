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
            'fase_id' => $this->fase_id,
            'llave' => $this->llave === null ? null : [
                'valor' => $this->llave->value,
                'etiqueta' => $this->llave->etiqueta(),
            ],
            'grupo' => $this->grupo,
            'ronda' => $this->ronda,
            'numero' => $this->numero,
            'mejor_de' => $this->mejor_de,
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'equipo_a' => new EquipoResumenResource($this->whenLoaded('equipoA')),
            'equipo_b' => new EquipoResumenResource($this->whenLoaded('equipoB')),
            'victorias_a' => $this->victorias_a,
            'victorias_b' => $this->victorias_b,
            'ganador_id' => $this->ganador_id,
            'es_descanso' => $this->es_descanso,
            'es_final' => $this->siguiente_ganador_id === null && $this->llave !== null,
            'detalle' => $this->detalle,
            'programado_para' => $this->programado_para?->toIso8601String(),
            'finalizado_en' => $this->finalizado_en?->toIso8601String(),

            'torneo' => $this->whenLoaded('torneo', fn () => [
                'nombre' => $this->torneo->nombre,
                'slug' => $this->torneo->slug,
                'juego' => $this->torneo->juego->value,
                'juego_nombre' => $this->torneo->juego->nombre(),
                'estado' => $this->torneo->estado->value,
            ]),
            'fase' => $this->whenLoaded('fase', fn () => [
                'nombre' => $this->fase->nombre,
                'tipo' => $this->fase->tipo->value,
            ]),
            'resultados' => $this->whenLoaded('resultadosBattleRoyale', fn () => $this->resultadosBattleRoyale->map(fn ($resultado): array => [
                'equipo' => $resultado->equipo->resumen(),
                'posicion' => $resultado->posicion,
                'bajas' => $resultado->bajas,
                'puntos' => $resultado->puntos,
            ])),
            'reportes' => ReporteResource::collection($this->whenLoaded('reportes')),
            'resuelto_por' => $this->whenLoaded('resolutor', fn () => $this->resolutor?->apodo),
        ];
    }
}

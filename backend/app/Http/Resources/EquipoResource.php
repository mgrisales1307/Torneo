<?php

namespace App\Http\Resources;

use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipo
 */
class EquipoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'abreviatura' => $this->abreviatura,
            'ciudad' => $this->ciudad,
            'estadio' => $this->estadio,
            'fundado_en' => $this->fundado_en?->toDateString(),
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,

            'colores' => [
                'primario' => $this->color_primario,
                'secundario' => $this->color_secundario,
            ],

            'imagenes' => [
                'escudo_url' => $this->urlDeImagen($this->escudo_ruta),
                'uniforme_local_url' => $this->urlDeImagen($this->uniforme_local_ruta),
                'uniforme_visitante_url' => $this->urlDeImagen($this->uniforme_visitante_ruta),
            ],

            'jugadores_count' => $this->whenCounted('jugadores'),
            'dueno' => new UsuarioResource($this->whenLoaded('dueno')),
            'jugadores' => JugadorResource::collection($this->whenLoaded('jugadores')),
            'inscripciones' => InscripcionResource::collection($this->whenLoaded('inscripciones')),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

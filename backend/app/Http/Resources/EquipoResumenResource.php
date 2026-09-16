<?php

namespace App\Http\Resources;

use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versión corta del equipo, la que va dentro de un partido o de la tabla: lo
 * justo para pintar el escudo y el nombre sin arrastrar toda la ficha.
 *
 * @mixin Equipo
 */
class EquipoResumenResource extends JsonResource
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
            'escudo_url' => $this->urlDeImagen($this->escudo_ruta),
            'color_primario' => $this->color_primario,
            'color_secundario' => $this->color_secundario,
        ];
    }
}

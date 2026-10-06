<?php

namespace App\Http\Resources;

use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versión corta del equipo, la que va dentro de un partido o de una tabla: lo
 * justo para pintar el logo y el nombre sin arrastrar toda la ficha.
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
        return $this->resource->resumen();
    }
}

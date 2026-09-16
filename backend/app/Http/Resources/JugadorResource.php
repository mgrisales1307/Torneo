<?php

namespace App\Http\Resources;

use App\Models\Jugador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de un jugador.
 *
 * No incluye documento, teléfono ni correo: son datos personales y no tienen
 * por qué salir en la plantilla que ve cualquier visitante. Para el dueño del
 * equipo y la organización existe JugadorPrivadoResource.
 *
 * @mixin Jugador
 */
class JugadorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipo_id' => $this->equipo_id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombreCompleto(),
            'numero_camiseta' => $this->numero_camiseta,
            'posicion' => [
                'valor' => $this->posicion->value,
                'etiqueta' => $this->posicion->etiqueta(),
                'abreviatura' => $this->posicion->abreviatura(),
            ],
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'es_capitan' => $this->es_capitan,
            'edad' => $this->edad(),
            'foto_url' => $this->urlDeImagen($this->foto_ruta),
            'equipo' => new EquipoResumenResource($this->whenLoaded('equipo')),
        ];
    }
}

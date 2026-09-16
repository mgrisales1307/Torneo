<?php

namespace App\Http\Resources;

use App\Models\Torneo;
use App\Soporte\Dinero;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Torneo
 */
class TorneoResource extends JsonResource
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
            'temporada' => $this->temporada,
            'categoria' => $this->categoria,
            'descripcion' => $this->descripcion,
            'ciudad' => $this->ciudad,
            'sede' => $this->sede,
            'logo_url' => $this->urlDeImagen($this->logo_ruta),

            'formato' => [
                'valor' => $this->formato->value,
                'etiqueta' => $this->formato->etiqueta(),
            ],
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],

            'reglas' => [
                'cupo_maximo_equipos' => $this->cupo_maximo_equipos,
                'minimo_jugadores_por_equipo' => $this->minimo_jugadores_por_equipo,
                'maximo_jugadores_por_equipo' => $this->maximo_jugadores_por_equipo,
                'puntos_por_victoria' => $this->puntos_por_victoria,
                'puntos_por_empate' => $this->puntos_por_empate,
                'puntos_por_derrota' => $this->puntos_por_derrota,
            ],

            'inscripcion' => [
                'abierta' => $this->admiteInscripciones(),
                'costo_centavos' => $this->costo_inscripcion_centavos,
                'costo_formateado' => $this->esGratuito()
                    ? 'Gratis'
                    : Dinero::formatear($this->costo_inscripcion_centavos, $this->moneda),
                'moneda' => $this->moneda,
                'abren_en' => $this->inscripciones_abren_en?->toIso8601String(),
                'cierran_en' => $this->inscripciones_cierran_en?->toIso8601String(),
                'equipos_inscritos' => $this->cantidadDeEquiposInscritos(),
                'cupos_disponibles' => $this->cuposDisponibles(),
            ],

            'fechas' => [
                'inicia_en' => $this->inicia_en?->toIso8601String(),
                'finaliza_en' => $this->finaliza_en?->toIso8601String(),
            ],

            'partidos_count' => $this->whenCounted('partidos'),
            'creador' => new UsuarioResource($this->whenLoaded('creador')),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

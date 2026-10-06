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
        $premios = array_values($this->premios ?? []);

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'juego' => [
                'valor' => $this->juego->value,
                'nombre' => $this->juego->nombre(),
                'abreviatura' => $this->juego->abreviatura(),
                'es_battle_royale' => $this->juego->esBattleRoyale(),
                'modalidad' => $this->juego->modalidad(),
            ],
            'formato' => [
                'valor' => $this->formato->value,
                'etiqueta' => $this->formato->etiqueta(),
            ],
            'estado' => [
                'valor' => $this->estado->value,
                'etiqueta' => $this->estado->etiqueta(),
            ],
            'reglas' => $this->reglas,

            'ajustes' => [
                'cupo_maximo_equipos' => $this->cupo_maximo_equipos,
                'mejor_de' => $this->mejor_de,
                'mejor_de_final' => $this->mejor_de_final,
                'cantidad_de_grupos' => $this->cantidad_de_grupos,
                'clasifican_por_grupo' => $this->clasifican_por_grupo,
                'rondas_suizo' => $this->rondas_suizo,
                'partidas_battle_royale' => $this->partidas_battle_royale,
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

            'check_in' => [
                'minutos' => $this->check_in_minutos,
                'abre_en' => $this->checkInAbreEn()?->toIso8601String(),
                'abierto' => $this->checkInAbierto(),
            ],

            'premios' => [
                'puestos' => array_map(fn (int $centavos, int $indice): array => [
                    'puesto' => $indice + 1,
                    'centavos' => $centavos,
                    'formateado' => Dinero::formatear($centavos, $this->moneda),
                ], $premios, array_keys($premios)),
                'total_centavos' => $this->bolsaDePremiosCentavos(),
                'total_formateado' => $this->bolsaDePremiosCentavos() > 0
                    ? Dinero::formatear($this->bolsaDePremiosCentavos(), $this->moneda)
                    : null,
            ],

            'fechas' => [
                'inicia_en' => $this->inicia_en?->toIso8601String(),
                'finalizado_en' => $this->finalizado_en?->toIso8601String(),
            ],

            'campeon' => new EquipoResumenResource($this->whenLoaded('campeon')),
            'partidos_count' => $this->whenCounted('partidos'),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

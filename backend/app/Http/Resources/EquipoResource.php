<?php

namespace App\Http\Resources;

use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha del equipo. El código de invitación solo sale para sus miembros: es
 * la llave para entrar al equipo.
 *
 * @mixin Equipo
 */
class EquipoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        $esMiembro = $usuario !== null
            && $this->relationLoaded('miembros')
            && $this->miembros->contains('id', $usuario->getKey());

        return [
            ...$this->resource->resumen(),
            'juego' => [
                'valor' => $this->juego->value,
                'nombre' => $this->juego->nombre(),
                'abreviatura' => $this->juego->abreviatura(),
                'titulares' => $this->juego->titulares(),
                'suplentes_maximos' => $this->juego->suplentesMaximos(),
                'cuenta' => $this->juego->nombreDeCuenta(),
            ],
            'capitan_id' => $this->capitan_id,
            'soy_capitan' => $this->resource->esCapitan($usuario),
            'soy_miembro' => $esMiembro,
            'codigo_invitacion' => $this->when($esMiembro, $this->codigo_invitacion),
            'miembros_count' => $this->whenCounted('miembros'),
            'miembros' => $this->whenLoaded('miembros', fn () => $this->miembros
                ->sortBy([
                    fn (Usuario $uno, Usuario $otro): int => ($otro->getKey() === $this->capitan_id) <=> ($uno->getKey() === $this->capitan_id),
                    fn (Usuario $uno, Usuario $otro): int => (int) $uno->pivot->es_suplente <=> (int) $otro->pivot->es_suplente,
                    fn (Usuario $uno, Usuario $otro): int => strcasecmp($uno->apodo, $otro->apodo),
                ])
                ->values()
                ->map(fn (Usuario $miembro): array => [
                    'id' => $miembro->getKey(),
                    'apodo' => $miembro->apodo,
                    'es_capitan' => $miembro->getKey() === $this->capitan_id,
                    'es_suplente' => (bool) $miembro->pivot->es_suplente,
                    'cuenta' => $miembro->relationLoaded('cuentasDeJuego')
                        ? $miembro->cuentaPara($this->juego)?->identificador
                        : null,
                ])),
            'inscripciones' => InscripcionResource::collection($this->whenLoaded('inscripciones')),
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}

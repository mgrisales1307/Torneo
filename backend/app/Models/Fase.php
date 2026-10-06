<?php

namespace App\Models;

use App\Enumeraciones\EstadoFase;
use App\Enumeraciones\TipoDeFase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una etapa del torneo: la fase de grupos, el cuadro de eliminación, las
 * rondas del suizo o las partidas de battle royale.
 *
 * @property TipoDeFase $tipo
 * @property EstadoFase $estado
 */
class Fase extends Model
{
    protected $table = 'fases';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'en_curso',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDeFase::class,
            'estado' => EstadoFase::class,
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Torneo, $this>
     */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /**
     * @return HasMany<Partido, $this>
     */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    public function estaFinalizada(): bool
    {
        return $this->estado === EstadoFase::Finalizada;
    }
}

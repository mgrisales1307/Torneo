<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Puesto y bajas de un equipo en una partida de battle royale. Los puntos se
 * guardan ya calculados con la tabla del juego.
 */
class ResultadoBattleRoyale extends Model
{
    protected $table = 'resultados_battle_royale';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'bajas' => 'integer',
            'puntos' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }
}

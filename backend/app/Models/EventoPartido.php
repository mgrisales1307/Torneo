<?php

namespace App\Models;

use App\Enumeraciones\TipoEventoPartido;
use Database\Factories\EventoPartidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gol o tarjeta dentro de un partido.
 *
 * @property TipoEventoPartido $tipo
 */
class EventoPartido extends Model
{
    /** @use HasFactory<EventoPartidoFactory> */
    use HasFactory;

    protected $table = 'eventos_partido';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'minuto',
        'detalle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoPartido::class,
            'minuto' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * @return BelongsTo<Jugador, $this>
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}

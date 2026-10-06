<?php

namespace App\Models;

use App\Enumeraciones\Juego;
use Database\Factories\CuentaDeJuegoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La cuenta de un jugador en un juego: su Riot ID, su Steam ID, su nombre en
 * PUBG o su UID de Free Fire. Sin ella no se puede inscribir en ese juego.
 *
 * @property Juego $juego
 */
class CuentaDeJuego extends Model
{
    /** @use HasFactory<CuentaDeJuegoFactory> */
    use HasFactory;

    protected $table = 'cuentas_de_juego';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'identificador',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'juego' => Juego::class,
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}

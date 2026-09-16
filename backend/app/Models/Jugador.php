<?php

namespace App\Models;

use App\Enumeraciones\EstadoJugador;
use App\Enumeraciones\PosicionJugador;
use App\Models\Rasgos\TieneImagenes;
use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Jugador de un equipo.
 *
 * @property EstadoJugador $estado
 * @property PosicionJugador $posicion
 */
class Jugador extends Model
{
    /** @use HasFactory<JugadorFactory> */
    use HasFactory, SoftDeletes, TieneImagenes;

    protected $table = 'jugadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombres',
        'apellidos',
        'documento_identidad',
        'fecha_nacimiento',
        'posicion',
        'numero_camiseta',
        'estado',
        'es_capitan',
        'telefono',
        'correo',
    ];

    /**
     * Valores por defecto de un modelo recién creado. Sin ellos, el objeto en
     * memoria no tendría estos atributos hasta releerlo de la base, y la
     * respuesta de la API saldría con huecos.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'posicion' => PosicionJugador::Mediocampista->value,
        'estado' => EstadoJugador::Activo->value,
        'es_capitan' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'posicion' => PosicionJugador::class,
            'estado' => EstadoJugador::class,
            'numero_camiseta' => 'integer',
            'es_capitan' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Al dar de baja a un jugador se libera su dorsal, para que el equipo
        // pueda asignárselo a otro sin chocar con el índice único.
        static::deleted(function (self $jugador): void {
            if ($jugador->trashed() && $jugador->numero_camiseta !== null) {
                $jugador->newQueryWithoutScopes()
                    ->whereKey($jugador->getKey())
                    ->update(['numero_camiseta' => null]);
            }
        });
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * @return HasMany<EventoPartido, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPartido::class);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeConvocables(Builder $consulta): void
    {
        $consulta->where('estado', EstadoJugador::Activo->value);
    }

    public function nombreCompleto(): string
    {
        return Str::squish($this->nombres.' '.$this->apellidos);
    }

    public function edad(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }
}

<?php

namespace App\Models;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Models\Rasgos\TieneImagenes;
use App\Models\Rasgos\TieneSlug;
use Database\Factories\TorneoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un torneo: las reglas de la competencia y su estado.
 *
 * @property EstadoTorneo $estado
 * @property FormatoTorneo $formato
 */
class Torneo extends Model
{
    /** @use HasFactory<TorneoFactory> */
    use HasFactory, SoftDeletes, TieneImagenes, TieneSlug;

    protected $table = 'torneos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'temporada',
        'categoria',
        'descripcion',
        'formato',
        'estado',
        'ciudad',
        'sede',
        'cupo_maximo_equipos',
        'minimo_jugadores_por_equipo',
        'maximo_jugadores_por_equipo',
        'puntos_por_victoria',
        'puntos_por_empate',
        'puntos_por_derrota',
        'costo_inscripcion_centavos',
        'moneda',
        'inscripciones_abren_en',
        'inscripciones_cierran_en',
        'inicia_en',
        'finaliza_en',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'formato' => FormatoTorneo::LigaIda->value,
        'estado' => EstadoTorneo::Borrador->value,
        'cupo_maximo_equipos' => 16,
        'minimo_jugadores_por_equipo' => 7,
        'maximo_jugadores_por_equipo' => 25,
        'puntos_por_victoria' => 3,
        'puntos_por_empate' => 1,
        'puntos_por_derrota' => 0,
        'costo_inscripcion_centavos' => 0,
        'moneda' => 'COP',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formato' => FormatoTorneo::class,
            'estado' => EstadoTorneo::class,
            'cupo_maximo_equipos' => 'integer',
            'minimo_jugadores_por_equipo' => 'integer',
            'maximo_jugadores_por_equipo' => 'integer',
            'puntos_por_victoria' => 'integer',
            'puntos_por_empate' => 'integer',
            'puntos_por_derrota' => 'integer',
            'costo_inscripcion_centavos' => 'integer',
            'inscripciones_abren_en' => 'datetime',
            'inscripciones_cierran_en' => 'datetime',
            'inicia_en' => 'datetime',
            'finaliza_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_id');
    }

    /**
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * @return HasMany<Partido, $this>
     */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    /**
     * Solo los torneos que el público puede ver: los que ya salieron de
     * borrador. Se aplica en todas las consultas públicas.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopePublicados(Builder $consulta): void
    {
        $consulta->whereNot('estado', EstadoTorneo::Borrador->value);
    }

    public function esGratuito(): bool
    {
        return $this->costo_inscripcion_centavos === 0;
    }

    /**
     * La ventana de inscripción manda sobre el estado: aunque el torneo esté
     * marcado como abierto, si la fecha de cierre pasó, no se admite a nadie.
     */
    public function admiteInscripciones(): bool
    {
        if (! $this->estado->admiteInscripciones()) {
            return false;
        }

        $ahora = now();

        if ($this->inscripciones_abren_en !== null && $ahora->lt($this->inscripciones_abren_en)) {
            return false;
        }

        if ($this->inscripciones_cierran_en !== null && $ahora->gt($this->inscripciones_cierran_en)) {
            return false;
        }

        return $this->cuposDisponibles() > 0;
    }

    public function cuposDisponibles(): int
    {
        return max(0, $this->cupo_maximo_equipos - $this->cantidadDeEquiposInscritos());
    }

    /**
     * Usa el conteo que venga precargado con `withCount` si está disponible.
     * Así el listado de torneos no dispara una consulta por fila.
     */
    public function cantidadDeEquiposInscritos(): int
    {
        $precargado = $this->getAttribute('inscripciones_que_ocupan_cupo_count');

        return $precargado !== null
            ? (int) $precargado
            : $this->inscripcionesQueOcupanCupo()->count();
    }

    /**
     * Ocupan cupo las inscripciones que ya pagaron o están aprobadas. Una
     * inscripción sin pagar no le quita el lugar a nadie.
     *
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripcionesQueOcupanCupo(): HasMany
    {
        return $this->inscripciones()->pagadas();
    }
}

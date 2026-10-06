<?php

namespace App\Models;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Rasgos\TieneSlug;
use Database\Factories\TorneoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un torneo: el juego, el formato, las reglas y su estado.
 *
 * @property Juego $juego
 * @property EstadoTorneo $estado
 * @property FormatoTorneo $formato
 * @property array<int, int>|null $premios
 */
class Torneo extends Model
{
    /** @use HasFactory<TorneoFactory> */
    use HasFactory, SoftDeletes, TieneSlug;

    protected $table = 'torneos';

    /**
     * El estado no está aquí: lo mueven las acciones de la organización
     * (publicar, iniciar, cancelar) y el avance de los resultados.
     *
     * @var list<string>
     */
    protected $fillable = [
        'juego',
        'nombre',
        'formato',
        'reglas',
        'cupo_maximo_equipos',
        'mejor_de',
        'mejor_de_final',
        'cantidad_de_grupos',
        'clasifican_por_grupo',
        'rondas_suizo',
        'partidas_battle_royale',
        'check_in_minutos',
        'costo_inscripcion_centavos',
        'moneda',
        'premios',
        'inscripciones_abren_en',
        'inscripciones_cierran_en',
        'inicia_en',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => EstadoTorneo::Borrador->value,
        'cupo_maximo_equipos' => 16,
        'mejor_de' => 1,
        'mejor_de_final' => 3,
        'check_in_minutos' => 30,
        'costo_inscripcion_centavos' => 0,
        'moneda' => 'COP',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'juego' => Juego::class,
            'formato' => FormatoTorneo::class,
            'estado' => EstadoTorneo::class,
            'cupo_maximo_equipos' => 'integer',
            'mejor_de' => 'integer',
            'mejor_de_final' => 'integer',
            'cantidad_de_grupos' => 'integer',
            'clasifican_por_grupo' => 'integer',
            'rondas_suizo' => 'integer',
            'partidas_battle_royale' => 'integer',
            'check_in_minutos' => 'integer',
            'costo_inscripcion_centavos' => 'integer',
            'premios' => 'array',
            'inscripciones_abren_en' => 'datetime',
            'inscripciones_cierran_en' => 'datetime',
            'inicia_en' => 'datetime',
            'finalizado_en' => 'datetime',
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
     * @return BelongsTo<Equipo, $this>
     */
    public function campeon(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'campeon_id')->withTrashed();
    }

    /**
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Los equipos que juegan: aprobados y con lugar en el cuadro.
     *
     * @return HasMany<Inscripcion, $this>
     */
    public function participantes(): HasMany
    {
        return $this->inscripciones()->aprobadas()->whereNotNull('semilla')->orderBy('semilla');
    }

    /**
     * @return HasMany<Fase, $this>
     */
    public function fases(): HasMany
    {
        return $this->hasMany(Fase::class)->orderBy('orden');
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

    public function usaCheckIn(): bool
    {
        return $this->check_in_minutos > 0;
    }

    public function checkInAbreEn(): ?Carbon
    {
        if (! $this->usaCheckIn() || $this->inicia_en === null) {
            return null;
        }

        return $this->inicia_en->copy()->subMinutes($this->check_in_minutos);
    }

    /**
     * El check-in abre los minutos indicados antes de la hora de inicio y no
     * cierra hasta que la organización inicia el torneo: quien llega tarde a la
     * hora pero antes del arranque todavía entra.
     */
    public function checkInAbierto(): bool
    {
        $abre = $this->checkInAbreEn();

        return $abre !== null
            && $this->estado->esperaInicio()
            && now()->gte($abre);
    }

    public function bolsaDePremiosCentavos(): int
    {
        return (int) array_sum($this->premios ?? []);
    }
}

<?php

namespace App\Models;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\LlaveDelCuadro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un partido del cuadro: una serie entre dos equipos o, en battle royale, una
 * partida en la que juegan todos.
 *
 * Los dos lados se llaman A y B, no local y visitante: en esports no hay
 * localía. A es el de mejor semilla cuando el cruce se arma al inicio.
 *
 * @property EstadoPartido $estado
 * @property LlaveDelCuadro|null $llave
 * @property array<int, array{mapa: string|null, ganador: string}>|null $detalle
 */
class Partido extends Model
{
    protected $table = 'partidos';

    /**
     * Solo la fecha es asignable: rivales, marcador y estado los escriben los
     * servicios que conocen el cuadro.
     *
     * @var list<string>
     */
    protected $fillable = [
        'programado_para',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => EstadoPartido::Pendiente->value,
        'mejor_de' => 1,
        'es_descanso' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPartido::class,
            'llave' => LlaveDelCuadro::class,
            'ronda' => 'integer',
            'numero' => 'integer',
            'mejor_de' => 'integer',
            'equipo_a_id' => 'integer',
            'equipo_b_id' => 'integer',
            'ganador_id' => 'integer',
            'siguiente_ganador_id' => 'integer',
            'siguiente_perdedor_id' => 'integer',
            'victorias_a' => 'integer',
            'victorias_b' => 'integer',
            'es_descanso' => 'boolean',
            'detalle' => 'array',
            'programado_para' => 'datetime',
            'finalizado_en' => 'datetime',
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
     * @return BelongsTo<Fase, $this>
     */
    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipoA(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_a_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipoB(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_b_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function ganador(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'ganador_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function siguienteGanador(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'siguiente_ganador_id');
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function siguientePerdedor(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'siguiente_perdedor_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resuelto_por_id');
    }

    /**
     * @return HasMany<ReporteDeResultado, $this>
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteDeResultado::class);
    }

    /**
     * @return HasMany<ResultadoBattleRoyale, $this>
     */
    public function resultadosBattleRoyale(): HasMany
    {
        return $this->hasMany(ResultadoBattleRoyale::class)->orderBy('posicion');
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeFinalizados(Builder $consulta): void
    {
        $consulta->where('estado', EstadoPartido::Finalizado->value);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeDelEquipo(Builder $consulta, int $equipoId): void
    {
        $consulta->where(function (Builder $filtro) use ($equipoId): void {
            $filtro->where('equipo_a_id', $equipoId)->orWhere('equipo_b_id', $equipoId);
        });
    }

    /**
     * Duelos con los dos rivales: ni descansos del suizo ni partidas de battle
     * royale.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeDuelos(Builder $consulta): void
    {
        $consulta->whereNotNull('equipo_a_id')->whereNotNull('equipo_b_id');
    }

    public function estaFinalizado(): bool
    {
        return $this->estado === EstadoPartido::Finalizado;
    }

    public function participa(int $equipoId): bool
    {
        return $this->equipo_a_id === $equipoId || $this->equipo_b_id === $equipoId;
    }

    /**
     * 'a' o 'b' según el lado en que juega el equipo.
     */
    public function ladoDe(int $equipoId): ?string
    {
        return match ($equipoId) {
            $this->equipo_a_id => 'a',
            $this->equipo_b_id => 'b',
            default => null,
        };
    }

    public function perdedorId(): ?int
    {
        if ($this->ganador_id === null || $this->es_descanso) {
            return null;
        }

        return $this->ganador_id === $this->equipo_a_id ? $this->equipo_b_id : $this->equipo_a_id;
    }

    /**
     * Mapas o partidas que hay que ganar para llevarse la serie.
     */
    public function victoriasParaGanar(): int
    {
        return intdiv($this->mejor_de, 2) + 1;
    }
}

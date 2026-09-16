<?php

namespace App\Models;

use App\Enumeraciones\EstadoPartido;
use Database\Factories\PartidoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un partido del calendario.
 *
 * @property EstadoPartido $estado
 */
class Partido extends Model
{
    /** @use HasFactory<PartidoFactory> */
    use HasFactory;

    protected $table = 'partidos';

    /**
     * El marcador no es asignable en masa: lo escribe el servicio que registra
     * el resultado a partir de los eventos del partido.
     *
     * @var list<string>
     */
    protected $fillable = [
        'programado_para',
        'sede',
        'arbitro',
        'observaciones',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => EstadoPartido::Programado->value,
        'es_vuelta' => false,
        'jornada' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPartido::class,
            'jornada' => 'integer',
            'es_vuelta' => 'boolean',
            'goles_local' => 'integer',
            'goles_visitante' => 'integer',
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
     * @return BelongsTo<Equipo, $this>
     */
    public function equipoLocal(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipoVisitante(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_id');
    }

    /**
     * @return HasMany<EventoPartido, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPartido::class, 'partido_id');
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
            $filtro->where('equipo_local_id', $equipoId)->orWhere('equipo_visitante_id', $equipoId);
        });
    }

    public function estaFinalizado(): bool
    {
        return $this->estado === EstadoPartido::Finalizado;
    }

    public function tieneMarcador(): bool
    {
        return $this->goles_local !== null && $this->goles_visitante !== null;
    }

    /**
     * Identificador del equipo ganador, o null si fue empate o no hay marcador.
     */
    public function ganadorId(): ?int
    {
        if (! $this->tieneMarcador() || $this->goles_local === $this->goles_visitante) {
            return null;
        }

        return $this->goles_local > $this->goles_visitante
            ? $this->equipo_local_id
            : $this->equipo_visitante_id;
    }

    public function participa(int $equipoId): bool
    {
        return $this->equipo_local_id === $equipoId || $this->equipo_visitante_id === $equipoId;
    }
}

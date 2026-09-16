<?php

namespace App\Models;

use App\Enumeraciones\EstadoInscripcion;
use Database\Factories\InscripcionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inscripción de un equipo en un torneo. Es la bisagra entre el equipo, el
 * cobro y la competencia: sin una inscripción aprobada, un equipo no aparece
 * en el calendario ni en la tabla.
 *
 * @property EstadoInscripcion $estado
 */
class Inscripcion extends Model
{
    /** @use HasFactory<InscripcionFactory> */
    use HasFactory;

    protected $table = 'inscripciones';

    /**
     * El estado no es asignable en masa: lo mueven los servicios de pago y de
     * revisión, que son los únicos que conocen las transiciones válidas.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grupo',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => EstadoInscripcion::PendientePago->value,
        'monto_centavos' => 0,
        'moneda' => 'COP',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoInscripcion::class,
            'monto_centavos' => 'integer',
            'revisado_en' => 'datetime',
            'pagada_en' => 'datetime',
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
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por_id');
    }

    /**
     * @return HasMany<Orden, $this>
     */
    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopePagadas(Builder $consulta): void
    {
        $consulta->whereIn('estado', [
            EstadoInscripcion::PendienteRevision->value,
            EstadoInscripcion::Aprobada->value,
        ]);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeAprobadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoInscripcion::Aprobada->value);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === EstadoInscripcion::Aprobada;
    }

    public function esperaPago(): bool
    {
        return $this->estado === EstadoInscripcion::PendientePago && $this->monto_centavos > 0;
    }
}

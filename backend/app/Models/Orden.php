<?php

namespace App\Models;

use App\Enumeraciones\EstadoOrden;
use Database\Factories\OrdenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de cobro de una inscripción.
 *
 * Ningún campo es asignable en masa: las órdenes las crea el gestor de cobros
 * con el monto tomado del torneo, nunca con datos que venga del cliente.
 *
 * @property EstadoOrden $estado
 */
class Orden extends Model
{
    /** @use HasFactory<OrdenFactory> */
    use HasFactory;

    protected $table = 'ordenes';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => EstadoOrden::Pendiente->value,
        'moneda' => 'COP',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoOrden::class,
            'monto_centavos' => 'integer',
            'expira_en' => 'datetime',
            'pagada_en' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'referencia';
    }

    /**
     * @return BelongsTo<Inscripcion, $this>
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopePendientes(Builder $consulta): void
    {
        $consulta->where('estado', EstadoOrden::Pendiente->value);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoOrden::Pendiente;
    }

    public function estaExpirada(): bool
    {
        return $this->expira_en !== null && $this->expira_en->isPast();
    }

    /**
     * Una orden se puede pagar si sigue pendiente y no se venció.
     */
    public function admitePago(): bool
    {
        return $this->estaPendiente() && ! $this->estaExpirada();
    }
}

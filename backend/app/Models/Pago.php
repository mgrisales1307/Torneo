<?php

namespace App\Models;

use App\Enumeraciones\EstadoPago;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transacción reportada por la pasarela.
 *
 * @property EstadoPago $estado
 */
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;

    protected $table = 'pagos';

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
            'estado' => EstadoPago::class,
            'monto_centavos' => 'integer',
            'carga_util' => 'array',
            'procesado_en' => 'datetime',
        ];
    }

    /**
     * La carga útil que envía la pasarela no se expone nunca en la API: puede
     * traer datos del medio de pago.
     *
     * @var list<string>
     */
    protected $hidden = [
        'carga_util',
    ];

    /**
     * @return BelongsTo<Orden, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    public function fueAprobado(): bool
    {
        return $this->estado === EstadoPago::Aprobado;
    }
}

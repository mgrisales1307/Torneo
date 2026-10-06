<?php

namespace App\Models;

use App\Models\Rasgos\TieneImagenes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El marcador que reporta el capitán de uno de los dos equipos, con la captura
 * que lo respalda. Si los dos reportes coinciden, el partido se cierra solo.
 *
 * @property array<int, array{mapa: string|null, ganador: string}>|null $detalle
 */
class ReporteDeResultado extends Model
{
    use TieneImagenes;

    protected $table = 'reportes_de_resultado';

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
            'victorias_a' => 'integer',
            'victorias_b' => 'integer',
            'detalle' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function coincideCon(self $otro): bool
    {
        return $this->victorias_a === $otro->victorias_a && $this->victorias_b === $otro->victorias_b;
    }
}

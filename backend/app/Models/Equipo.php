<?php

namespace App\Models;

use App\Models\Rasgos\TieneImagenes;
use App\Models\Rasgos\TieneSlug;
use Database\Factories\EquipoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Un equipo pertenece a un dueño, que es quien paga la inscripción y gestiona
 * la plantilla.
 */
class Equipo extends Model
{
    /** @use HasFactory<EquipoFactory> */
    use HasFactory, SoftDeletes, TieneImagenes, TieneSlug;

    protected $table = 'equipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'abreviatura',
        'ciudad',
        'estadio',
        'fundado_en',
        'descripcion',
        'color_primario',
        'color_secundario',
        'activo',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color_primario' => '#0B1D17',
        'color_secundario' => '#EAB857',
        'activo' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fundado_en' => 'date',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $equipo): void {
            if ($equipo->isDirty('abreviatura') && filled($equipo->abreviatura)) {
                $equipo->abreviatura = Str::upper($equipo->abreviatura);
            }
        });
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function dueno(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'dueno_id');
    }

    /**
     * @return HasMany<Jugador, $this>
     */
    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class);
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
    public function partidosComoLocal(): HasMany
    {
        return $this->hasMany(Partido::class, 'equipo_local_id');
    }

    /**
     * @return HasMany<Partido, $this>
     */
    public function partidosComoVisitante(): HasMany
    {
        return $this->hasMany(Partido::class, 'equipo_visitante_id');
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeDelDueno(Builder $consulta, Usuario $dueno): void
    {
        $consulta->where('dueno_id', $dueno->getKey());
    }

    /**
     * Rutas de las imágenes del equipo, para poder borrarlas del disco cuando
     * se reemplazan o el equipo se elimina.
     *
     * @return array<int, string>
     */
    public function rutasDeImagenes(): array
    {
        return array_values(array_filter([
            $this->escudo_ruta,
            $this->uniforme_local_ruta,
            $this->uniforme_visitante_ruta,
        ]));
    }
}

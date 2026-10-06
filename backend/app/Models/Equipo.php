<?php

namespace App\Models;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Rasgos\TieneImagenes;
use App\Models\Rasgos\TieneSlug;
use Database\Factories\EquipoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Un equipo de un juego. Lo crea su capitán, que es quien lo inscribe y paga;
 * los demás entran con el código de invitación.
 *
 * @property Juego $juego
 */
class Equipo extends Model
{
    /** @use HasFactory<EquipoFactory> */
    use HasFactory, SoftDeletes, TieneImagenes, TieneSlug;

    protected $table = 'equipos';

    /**
     * El juego y el capitán no son asignables en masa: el juego no cambia
     * nunca, y la capitanía se cede con GestorDeEquipos.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'abreviatura',
        'color',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => '#FF4655',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'juego' => Juego::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $equipo): void {
            if (blank($equipo->codigo_invitacion)) {
                $equipo->codigo_invitacion = self::codigoNuevo();
            }
        });

        static::saving(function (self $equipo): void {
            if ($equipo->isDirty('abreviatura') && filled($equipo->abreviatura)) {
                $equipo->abreviatura = Str::upper($equipo->abreviatura);
            }
        });
    }

    /**
     * Ocho caracteres sin los que se confunden al dictarlos (0/O, 1/I/L).
     */
    public static function codigoNuevo(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $codigo = '';

            for ($i = 0; $i < 8; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (self::withTrashed()->where('codigo_invitacion', $codigo)->exists());

        return $codigo;
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capitan(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capitan_id');
    }

    /**
     * @return BelongsToMany<Usuario, $this>
     */
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'miembros_equipo')
            ->withPivot('es_suplente')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Inscripcion, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeDelMiembro(Builder $consulta, Usuario $usuario): void
    {
        $consulta->whereHas('miembros', fn (Builder $miembros) => $miembros->whereKey($usuario->getKey()));
    }

    public function esCapitan(?Usuario $usuario): bool
    {
        return $usuario !== null && $usuario->getKey() === $this->capitan_id;
    }

    public function tieneMiembro(Usuario $usuario): bool
    {
        return $this->miembros()->whereKey($usuario->getKey())->exists();
    }

    /**
     * Un equipo que está jugando un torneo no cambia de plantilla hasta que el
     * torneo termine.
     */
    public function estaCompitiendo(): bool
    {
        return $this->inscripciones()
            ->aprobadas()
            ->whereHas('torneo', fn (Builder $torneo) => $torneo->where('estado', EstadoTorneo::EnCurso->value))
            ->exists();
    }

    /**
     * Lo mínimo para pintar al equipo en una tabla o en un cuadro. Las tablas
     * se guardan en caché como arreglos, por eso no es un Resource.
     *
     * @return array{id: int, nombre: string, slug: string, abreviatura: string, color: string, logo_url: string|null}
     */
    public function resumen(): array
    {
        return [
            'id' => $this->getKey(),
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'abreviatura' => $this->abreviatura,
            'color' => $this->color,
            'logo_url' => $this->urlDeImagen($this->logo_ruta),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function rutasDeImagenes(): array
    {
        return array_values(array_filter([$this->logo_ruta]));
    }
}

<?php

namespace App\Models;

use App\Enumeraciones\Juego;
use App\Enumeraciones\RolUsuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de la plataforma.
 *
 * La tabla y sus columnas están en español; los tres métodos `getAuth*` y
 * `routeNotification*` son los puntos por los que Laravel pregunta el nombre
 * real de la columna, así que todo el framework (login, recordarme,
 * restablecer contraseña, correos) sigue funcionando sin parches.
 *
 * El apodo es el nombre público; el nombre real y el correo no salen nunca en
 * las páginas públicas.
 *
 * @property-read string $nombre
 * @property-read string $apodo
 * @property-read string $correo
 * @property-read RolUsuario $rol
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'apodo',
        'correo',
        'telefono',
        'clave',
        'rol',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'clave',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'clave' => 'hashed',
            'rol' => RolUsuario::class,
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'clave';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(Notification $notification): string
    {
        return $this->correo;
    }

    /**
     * @return HasMany<CuentaDeJuego, $this>
     */
    public function cuentasDeJuego(): HasMany
    {
        return $this->hasMany(CuentaDeJuego::class);
    }

    /**
     * Equipos en los que juega, sea como capitán, titular o suplente.
     *
     * @return BelongsToMany<Equipo, $this>
     */
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'miembros_equipo')
            ->withPivot('es_suplente')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Equipo, $this>
     */
    public function equiposQueCapitanea(): HasMany
    {
        return $this->hasMany(Equipo::class, 'capitan_id');
    }

    /**
     * @return HasMany<Torneo, $this>
     */
    public function torneosCreados(): HasMany
    {
        return $this->hasMany(Torneo::class, 'creado_por_id');
    }

    /**
     * @return HasMany<Orden, $this>
     */
    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class, 'usuario_id');
    }

    public function cuentaPara(Juego $juego): ?CuentaDeJuego
    {
        return $this->cuentasDeJuego->firstWhere('juego', $juego);
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Administrador;
    }

    /**
     * Personal que organiza la competencia.
     */
    public function perteneceAlStaff(): bool
    {
        return in_array($this->rol, [RolUsuario::Administrador, RolUsuario::Empleado], true);
    }

    public function tieneAlgunRol(RolUsuario ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }
}

<?php

namespace App\Providers;

use App\Servicios\Pagos\FabricaDePasarelas;
use App\Servicios\Pagos\PasarelaDePagos;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Quien pida «la pasarela» recibe la que diga config/pagos.php. El
        // resto del código nunca nombra a un proveedor concreto.
        $this->app->bind(PasarelaDePagos::class, fn ($app): PasarelaDePagos => $app
            ->make(FabricaDePasarelas::class)
            ->activa());
    }

    public function boot(): void
    {
        $this->configurarModelos();
        $this->configurarLimitesDePeticiones();
    }

    private function configurarModelos(): void
    {
        // En desarrollo, asignar un atributo que el modelo no declara fillable
        // deja de ser un descarte silencioso y pasa a ser un error visible.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * Límites de peticiones.
     *
     * El de ingreso va por correo + IP: frena la fuerza bruta contra una cuenta
     * concreta sin dejar que alguien bloquee a otro usuario solo con fallar su
     * contraseña muchas veces desde otra red.
     */
    private function configurarLimitesDePeticiones(): void
    {
        RateLimiter::for('api', fn (Request $peticion): Limit => Limit::perMinute(120)
            ->by($peticion->user()?->getAuthIdentifier() ?? $peticion->ip()));

        RateLimiter::for('ingreso', function (Request $peticion): Limit {
            $correo = Str::transliterate(Str::lower($peticion->string('correo')->toString()));

            return Limit::perMinute(5)->by($correo.'|'.$peticion->ip());
        });

        RateLimiter::for('registro', fn (Request $peticion): Limit => Limit::perHour(10)->by($peticion->ip()));

        RateLimiter::for('pagos', fn (Request $peticion): Limit => Limit::perMinute(20)
            ->by($peticion->user()?->getAuthIdentifier() ?? $peticion->ip()));

        RateLimiter::for('subidas', fn (Request $peticion): Limit => Limit::perMinute(30)
            ->by($peticion->user()?->getAuthIdentifier() ?? $peticion->ip()));

        // Un código de invitación es la llave de un equipo: probar códigos al
        // azar tiene que salir caro.
        RateLimiter::for('unirse', fn (Request $peticion): Limit => Limit::perMinute(10)
            ->by($peticion->user()?->getAuthIdentifier() ?? $peticion->ip()));
    }
}

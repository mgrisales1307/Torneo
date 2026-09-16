<?php

namespace App\Servicios\Pagos;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resuelve la pasarela configurada, y también una concreta por su nombre
 * (la notificación llega a /pagos/notificaciones/{pasarela}, porque un mismo
 * proyecto puede aceptar varias).
 */
class FabricaDePasarelas
{
    public function __construct(private readonly Container $contenedor) {}

    public function activa(): PasarelaDePagos
    {
        return $this->porNombre((string) config('pagos.pasarela'));
    }

    public function porNombre(string $nombre): PasarelaDePagos
    {
        $clase = config("pagos.pasarelas.$nombre.clase");

        if (! is_string($clase) || ! class_exists($clase)) {
            throw new InvalidArgumentException("La pasarela de pagos «$nombre» no está registrada en config/pagos.php.");
        }

        $pasarela = $this->contenedor->make($clase);

        if (! $pasarela instanceof PasarelaDePagos) {
            throw new InvalidArgumentException("La pasarela «$nombre» no implementa PasarelaDePagos.");
        }

        return $pasarela;
    }

    /**
     * @return array<int, string>
     */
    public function nombresRegistrados(): array
    {
        return array_keys((array) config('pagos.pasarelas', []));
    }
}

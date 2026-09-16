<?php

namespace App\Models\Rasgos;

use App\Servicios\Medios\AlmacenDeImagenes;

/**
 * Traduce una ruta guardada en la base de datos a la URL con la que el
 * navegador pide la imagen. La política de nombres y de disco vive en
 * AlmacenDeImagenes; aquí solo se le pregunta.
 */
trait TieneImagenes
{
    public function urlDeImagen(?string $ruta): ?string
    {
        return app(AlmacenDeImagenes::class)->url($ruta);
    }
}

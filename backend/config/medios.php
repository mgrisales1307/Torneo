<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de las imágenes
    |--------------------------------------------------------------------------
    |
    | Logos de equipo y capturas de resultados van al disco `public`, que se
    | expone mediante el enlace simbólico `public/storage`. Los nombres de
    | archivo los genera el servidor: el nombre del navegador nunca se usa.
    |
    */

    'disco' => env('DISCO_MEDIOS', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Formatos aceptados
    |--------------------------------------------------------------------------
    |
    | Solo mapas de bits. SVG queda fuera a propósito: es XML, puede traer
    | scripts dentro y se serviría desde nuestro propio dominio.
    |
    */

    'extensiones' => ['jpg', 'jpeg', 'png', 'webp'],

    'tipos_mime' => ['image/jpeg', 'image/png', 'image/webp'],

    /*
    |--------------------------------------------------------------------------
    | Límites por tipo de imagen
    |--------------------------------------------------------------------------
    */

    'limites' => [
        'logo' => ['kb' => 2048, 'minimo_px' => 120, 'maximo_px' => 2000],

        // La captura del marcador final: sale de una pantalla, así que admite
        // resoluciones de monitor y de móvil.
        'captura' => ['kb' => (int) env('CAPTURA_MAX_KB', 6144), 'minimo_px' => 200, 'maximo_px' => 4000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Carpetas
    |--------------------------------------------------------------------------
    */

    'carpetas' => [
        'logo' => 'logos',
        'captura' => 'capturas',
    ],

];

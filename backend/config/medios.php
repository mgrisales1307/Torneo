<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de las imágenes
    |--------------------------------------------------------------------------
    |
    | Escudos, uniformes y fotos van al disco `public`, que se expone mediante
    | el enlace simbólico `public/storage`. Los nombres de archivo los genera
    | el servidor: el nombre que manda el navegador nunca se usa.
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
        'escudo' => ['kb' => 2048, 'minimo_px' => 120, 'maximo_px' => 2000],
        'uniforme' => ['kb' => 3072, 'minimo_px' => 200, 'maximo_px' => 2400],
        'foto_jugador' => ['kb' => 2048, 'minimo_px' => 120, 'maximo_px' => 2000],
        'logo_torneo' => ['kb' => 2048, 'minimo_px' => 120, 'maximo_px' => 2000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Carpetas
    |--------------------------------------------------------------------------
    */

    'carpetas' => [
        'escudo' => 'escudos',
        'uniforme' => 'uniformes',
        'foto_jugador' => 'jugadores',
        'logo_torneo' => 'torneos',
    ],

];

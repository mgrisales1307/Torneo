<?php

return [
    'habilitadas' => (bool) env('METRICAS_HABILITADAS', false),
    'zona_horaria' => 'America/Bogota',
    'retencion_dias' => 90,
    'paginas' => ['inicio', 'juego', 'torneos', 'torneo', 'partido', 'equipo', 'jugador', 'ingreso', 'registro', 'panel', 'otra'],
    'metabase' => [
        'url' => env('METABASE_URL'),
        'url_interna' => env('METABASE_URL_INTERNA', env('METABASE_URL')),
        'clave' => env('METABASE_SECRET_KEY'),
        'correo' => env('METABASE_ADMIN_EMAIL'),
        'contrasena' => env('METABASE_ADMIN_PASSWORD'),
        'contrasena_lector' => env('METRICAS_LECTOR_PASSWORD'),
    ],
];

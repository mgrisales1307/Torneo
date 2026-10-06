<?php

use App\Servicios\Pagos\PasarelaSimulada;

return [

    /*
    |--------------------------------------------------------------------------
    | Pasarela activa
    |--------------------------------------------------------------------------
    |
    | Nombre de la pasarela con la que se cobran las inscripciones. Cambiar de
    | proveedor (Wompi, Mercado Pago, Stripe, PayU) es registrar su clase aquí
    | abajo y cambiar esta variable: ni los controladores ni los modelos saben
    | con qué proveedor se está cobrando.
    |
    */

    'pasarela' => env('PASARELA_PAGOS', 'simulada'),

    'moneda' => env('PAGOS_MONEDA', 'COP'),

    /*
    |--------------------------------------------------------------------------
    | Vigencia de una orden
    |--------------------------------------------------------------------------
    |
    | Minutos que una orden de pago acepta ser pagada. Pasado ese tiempo el
    | capitán del equipo genera una nueva: así un enlace de pago filtrado deja de
    | servir, y el cupo del torneo no queda bloqueado indefinidamente.
    |
    */

    'minutos_de_vigencia' => (int) env('PAGOS_MINUTOS_VIGENCIA', 60),

    /*
    |--------------------------------------------------------------------------
    | Pasarelas registradas
    |--------------------------------------------------------------------------
    */

    'pasarelas' => [

        'simulada' => [
            'clase' => PasarelaSimulada::class,

            // Con este secreto se firma y se verifica cada notificación. Si
            // falta, la pasarela se niega a operar en vez de aceptar avisos de
            // pago sin firmar.
            'secreto' => env('PASARELA_SIMULADA_SECRETO', env('APP_KEY')),

            // Margen de tolerancia, en segundos, para la marca de tiempo de la
            // notificación. Evita que una notificación capturada hoy se pueda
            // reenviar mañana.
            'tolerancia_en_segundos' => (int) env('PASARELA_SIMULADA_TOLERANCIA', 300),
        ],

    ],

];

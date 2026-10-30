<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (is_file(public_path('index.html'))) {
        return response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache']);
    }

    return view('welcome');
});

Route::get('/{ruta}', function () {
    abort_unless(is_file(public_path('index.html')), 404);

    return response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache']);
})->where('ruta', '(?!api(?:/|$)|sanctum(?:/|$)|storage(?:/|$)|up(?:/|$)|assets(?:/|$)).*');

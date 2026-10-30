<?php

$almacenTemporal = '/tmp/nexzone/storage';
$_ENV['LARAVEL_STORAGE_PATH'] = $almacenTemporal;
$_SERVER['LARAVEL_STORAGE_PATH'] = $almacenTemporal;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

foreach (['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directorio) {
    $ruta = $almacenTemporal.'/'.$directorio;
    if (! is_dir($ruta) && ! mkdir($ruta, 0700, true) && ! is_dir($ruta)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento temporal.');
    }
}

require __DIR__.'/../public/index.php';

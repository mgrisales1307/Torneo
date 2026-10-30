<?php

// Solo lectura: compara la copia SQLite con la conexión PostgreSQL del entorno.
$origen = new PDO('sqlite:'.($argv[1] ?? throw new RuntimeException('Falta la copia SQLite.')));
$destino = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$esquema = getenv('DB_SCHEMA') ?: 'nexzone';
$identificador = fn (string $valor): string => '"'.str_replace('"', '""', $valor).'"';
$tablas = $origen->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$total = 0;
foreach ($tablas as $tabla) {
    if (in_array('--sin-sesiones-cache', $argv, true) && in_array($tabla, ['sessions', 'cache', 'cache_locks'], true)) {
        continue;
    }
    $columnas = $origen->query('PRAGMA table_info('.$identificador($tabla).')')->fetchAll(PDO::FETCH_ASSOC);
    $originales = $origen->query('SELECT * FROM '.$identificador($tabla))->fetchAll(PDO::FETCH_ASSOC);
    $remotos = $destino->query('SELECT * FROM '.$identificador($esquema).'.'.$identificador($tabla))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($remotos as &$fila) {
        foreach ($columnas as $columna) {
            $nombre = $columna['name'];
            if ($fila[$nombre] !== null && strtolower($columna['type']) === 'tinyint(1)') {
                $fila[$nombre] = (int) $fila[$nombre];
            }
        }
    }
    unset($fila);
    $canonizar = function (array $filas): array {
        $salida = array_map(function (array $fila): string {
            ksort($fila);
            return json_encode($fila, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }, $filas);
        sort($salida);
        return $salida;
    };
    if ($canonizar($originales) !== $canonizar($remotos)) {
        throw new RuntimeException('Los campos no coinciden en '.$tabla);
    }
    $total += count($originales);
    echo $tabla.': '.count($originales)." registros idénticos\n";
}
echo $total." registros verificados campo por campo.\n";

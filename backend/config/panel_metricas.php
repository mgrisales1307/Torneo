<?php

/**
 * Preguntas reproducibles de Metabase. El SQL solo consulta el almacén agregado.
 * Las series cubren 30 días de calendario en Bogotá; los inventarios son actuales.
 */
$ventana = "fecha >= (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date - 29";
$juego = "CASE juego WHEN 'valorant' THEN 'Valorant' WHEN 'lol' THEN 'League of Legends' WHEN 'dota2' THEN 'Dota 2' WHEN 'pubg' THEN 'PUBG' WHEN 'freefire' THEN 'Free Fire' ELSE juego END";
$estado = "INITCAP(REPLACE(estado, '_', ' '))";

return [
    ['nombre' => 'Visitas · 30 días', 'tipo' => 'scalar', 'sql' => "SELECT COALESCE(SUM(visitas), 0) AS visitas FROM trafico_diario WHERE $ventana", 'ancho' => 6, 'alto' => 4],
    ['nombre' => 'Sesiones diarias acumuladas · 30 días', 'titulo' => 'Sesiones · 30 días', 'tipo' => 'scalar', 'sql' => "SELECT COALESCE(SUM(sesiones), 0) AS sesiones FROM sesiones_diarias WHERE $ventana", 'ancho' => 6, 'alto' => 4],
    ['nombre' => 'Jugadores registrados · total actual', 'titulo' => 'Jugadores registrados', 'tipo' => 'scalar', 'sql' => 'SELECT jugadores FROM resumen_plataforma', 'ancho' => 6, 'alto' => 4],
    ['nombre' => 'Inscripciones confirmadas · total actual', 'titulo' => 'Inscripciones confirmadas', 'tipo' => 'scalar', 'sql' => 'SELECT confirmadas FROM resumen_plataforma', 'ancho' => 6, 'alto' => 4],
    ['nombre' => 'Visitas por día', 'tipo' => 'line', 'sql' => "SELECT fecha, SUM(visitas) AS visitas FROM trafico_diario WHERE $ventana GROUP BY fecha ORDER BY fecha", 'ancho' => 16, 'alto' => 7, 'x' => 'fecha', 'y' => ['visitas']],
    ['nombre' => 'Dispositivos · 30 días', 'tipo' => 'pie', 'sql' => "SELECT CASE dispositivo WHEN 'movil' THEN 'Móvil' WHEN 'tableta' THEN 'Tableta' ELSE 'Escritorio' END AS dispositivo, SUM(visitas) AS visitas FROM trafico_diario WHERE $ventana GROUP BY dispositivo ORDER BY visitas DESC", 'ancho' => 8, 'alto' => 7],
    ['nombre' => 'Páginas más consultadas · 30 días', 'tipo' => 'bar', 'sql' => "SELECT pagina, SUM(visitas) AS visitas FROM trafico_diario WHERE $ventana GROUP BY pagina ORDER BY visitas DESC", 'ancho' => 12, 'alto' => 7, 'x' => 'pagina', 'y' => ['visitas']],
    ['nombre' => 'Inscripciones por juego · total actual', 'tipo' => 'bar', 'sql' => "SELECT $juego AS juego, SUM(inscripciones) AS inscripciones, SUM(confirmadas) AS confirmadas FROM torneos_por_juego GROUP BY juego ORDER BY inscripciones DESC", 'ancho' => 12, 'alto' => 7, 'x' => 'juego', 'y' => ['inscripciones', 'confirmadas']],
    ['nombre' => 'Registros e inscripciones por día · 30 días', 'tipo' => 'line', 'sql' => "SELECT fecha, registros, inscripciones FROM actividad_diaria WHERE $ventana ORDER BY fecha", 'ancho' => 12, 'alto' => 7, 'x' => 'fecha', 'y' => ['registros', 'inscripciones']],
    ['nombre' => 'Estado de inscripciones · inventario actual', 'tipo' => 'bar', 'sql' => "SELECT $estado AS estado, cantidad FROM inscripciones_por_estado ORDER BY cantidad DESC", 'ancho' => 12, 'alto' => 7, 'x' => 'estado', 'y' => ['cantidad']],
    ['nombre' => 'Rendimiento diario · p75 y muestras (LCP/INP en ms; CLS sin unidad)', 'tipo' => 'table', 'sql' => "SELECT fecha AS \"Fecha\", UPPER(metrica) AS \"Métrica\", ROUND(p75::numeric, 3) AS p75, muestras AS \"Muestras\" FROM rendimiento_diario WHERE $ventana ORDER BY fecha DESC, metrica", 'ancho' => 12, 'alto' => 7],
    ['nombre' => 'Torneos, cupos y participación · inventario actual', 'tipo' => 'table', 'sql' => "SELECT $juego AS \"Juego\", $estado AS \"Estado\", torneos AS \"Torneos\", cupos AS \"Cupos\", inscripciones AS \"Inscripciones\", confirmadas AS \"Confirmadas\" FROM torneos_por_juego ORDER BY juego, estado", 'ancho' => 12, 'alto' => 7],
    ['nombre' => 'Pagos virtuales netos vigentes · COP · 30 días', 'tipo' => 'line', 'sql' => "SELECT fecha, pagos_virtuales_cop FROM actividad_diaria WHERE $ventana ORDER BY fecha", 'ancho' => 12, 'alto' => 6, 'x' => 'fecha', 'y' => ['pagos_virtuales_cop']],
    ['nombre' => 'Actualización y totales · dinero exclusivamente virtual', 'tipo' => 'table', 'sql' => 'SELECT actualizado_en AS "Actualizado", equipos AS "Equipos", torneos AS "Torneos", pagos_virtuales_cop AS "Pagos virtuales (COP)" FROM resumen_plataforma', 'ancho' => 12, 'alto' => 6],
];

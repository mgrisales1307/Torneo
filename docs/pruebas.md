# Pruebas y criterios de entrega

## Automatizadas

```bash
docker compose exec api php artisan test
cd frontend
npm test
npm run lint
npm run build
```

Prueba enfocada: `docker compose exec api php artisan test --filter=MetricasTest`.
El backend usa SQLite en memoria, caché de prueba y transacciones de preparación;
las pruebas no deben apuntar a producción. Las credenciales `.env` no se requieren
en GitHub Actions.

La suite cubre el dominio competitivo, pagos virtuales, cartera, equipos,
autenticación, roles y publicación. La nueva cobertura incluye contrato de
eventos, privacidad, deduplicación, actualización de muestras, rate limit, firma
y caducidad del JWT, panel ausente, días de Bogotá, sesiones únicas por día y p75.
React verifica consentimiento, retiro, DNT/GPC, servidor desactivado, rutas sin
identidad, rotación de sesión y visitas sin duplicados bajo StrictMode.

Los tests unitarios no sustituyen comprobar el Metabase instalado: las APIs del
proveedor cambian entre versiones. Repetir el flujo siguiente tras actualizarlo.

## Verificación real de Metabase

1. Arrancar los servicios de [métricas](metricas.md). Verificar API y PostgreSQL
   sanos y `GET http://localhost:3001/api/health` con `status: ok`.
2. Ejecutar `metricas:sincronizar` y `metricas:preparar`. Repetir preparación y
   confirmar que se mantienen una colección, un dashboard y 14 preguntas.
3. Verificar que `metricas_lector` puede consultar las siete tablas y no puede
   escribir ni conectar a la base del catálogo de Metabase.
4. Sin sesión, `/api/v1/admin/metricas` devuelve 401; con jugador devuelve 403.
   Administrador y empleado pueden consultar el dashboard.
5. En navegador, rechazar captura y comprobar que navegar no añade eventos;
   aceptar, recorrer inicio y torneos y comprobar que los eventos llegan con CSRF.
6. Interactuar, ocultar y volver a la pestaña. Comprobar muestras disponibles
   según soporte del navegador, sin exigir INP a una página sin interacción.
7. Sincronizar y revisar que las 14 tarjetas consultan datos reales sin errores.
   Si todavía no hay tráfico, el cero y el estado vacío son resultados válidos.
8. Actualizar el panel y verificar fecha reciente, conservación del acceso y
   renovación del JWT. Comprobar el comportamiento con Metabase detenido.
9. Revisar el panel y consentimiento en escritorio y móvil, navegación por
   teclado, títulos, contraste y desplazamiento de tablas.

## Registro de esta entrega

2026-10-29: 161 pruebas backend y 15 frontend correctas; lint y compilación del
frontend correctos. La verificación local utiliza las versiones registradas en
[métricas](metricas.md). No se afirma una validación de producción.

Se aplicaron correcciones compatibles del lockfile del frontend y la auditoría
final de npm informó cero vulnerabilidades conocidas. La auditoría de Composer
no pudo consultar Packagist por un timeout de red; ese resultado no equivale a
una auditoría de seguridad aprobada.

## CI de GitHub

`.github/workflows/ci.yml` ejecuta PHP/Composer y frontend en trabajos separados,
usando archivos lock. Las pruebas nuevas se incluyen en los comandos habituales.
La instalación real de Metabase se verifica localmente o en un entorno de revisión;
el workflow normal no dispone de secretos ni accede al sitio publicado.

Antes de una entrega: contratos y permisos correctos, fallos controlados,
pruebas relevantes, lint/build, verificación real, documentación actualizada y
ausencia de secretos en el diff. No usar una captura de pantalla como sustituto
de consultas y autorización comprobadas.

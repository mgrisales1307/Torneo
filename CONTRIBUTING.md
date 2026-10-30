# Contribuir a NexZone

## Preparar el entorno

Lee el [README](README.md) y [arquitectura](docs/arquitectura.md). Inicia Docker y
la SPA según esas guías. Para analítica, sigue [métricas](docs/metricas.md).
Mantén archivos `.env`, bases locales y credenciales fuera de Git.

## Convenciones

- Código y mensajes en español; conservar los nombres impuestos por frameworks.
- Reutilizar controladores por recurso, Form Requests, políticas y servicios
  actuales. Las reglas del negocio no pertenecen a componentes React.
- Ramas de trabajo descriptivas, por ejemplo `codex/metricas-metabase`.
- Commits que expliquen el cambio y su motivo. Separar cambios ajenos al alcance.
- No cambiar importes de centavos a flotantes en el negocio. La conversión a COP
  del dashboard es exclusivamente una presentación agregada.
- No mover el frontend a otro framework ni reemplazar autenticación o base de
  datos para una tarea que no lo requiera.

## Cambios y pruebas

Antes de una PR, ejecutar los comandos de [pruebas](docs/pruebas.md), revisar
`git diff --check` y comprobar el comportamiento visible. PHP usa Laravel Pint;
puede formatearse una lista explícita de archivos con `vendor/bin/pint RUTAS`.
No reformatear archivos ajenos para que una PR parezca más grande.

Probar contratos, permisos, fallos y regresiones del dominio. En frontend, revisar
teclado, móvil, carga, vacío y error. Añadir pruebas por comportamiento nuevo o
un defecto reproducido, no pruebas que solo repitan la implementación.

## Esquema y despliegues

Toda migración debe explicar su reversión y la conservación de datos. Desarrollo
usa SQLite; producción usa PostgreSQL con permisos limitados y RLS. Un cambio
debe ser compatible con ambos. Copiar antes de migrar producción; no usar seeders
de demostración ni `migrate:fresh` allí.

Las decisiones relevantes se documentan en `docs/decisiones`. Actualizar la guía
afectada y `CHANGELOG.md`. Si cambia un endpoint de métricas, mantener su OpenAPI.
Los comentarios del código explican invariantes y motivos, evitando narrar líneas
obvias o duplicar manuales completos dentro de funciones.

## Revisión y reporte

Utiliza la plantilla de PR para describir problema, comportamiento final,
validación y riesgos reales. Reporta errores reproducibles con la plantilla de
issue, sin tokens, cookies, correos de usuarios ni dumps de producción.
Para vulnerabilidades, sigue [SECURITY.md](SECURITY.md).

## Derechos de uso

Las dependencias mantienen sus licencias. Pitagon Sans incluye OFL en sus assets.
El material de videojuegos se utiliza para demostración académica según el README.
El repositorio no concede automáticamente una licencia global sobre el código
original ni sobre marcas y recursos de terceros; su titular debe decidirla
explícitamente antes de una redistribución comercial.

# Despliegue y recuperación

## Aplicación existente

La SPA y Laravel se publican juntos en Vercel para mantener el mismo origen de
Sanctum. PostgreSQL y los medios viven en Supabase. El procedimiento detallado,
el proyecto enlazado y las variables existentes están en el [README](../README.md).

Instalar el CLI con `npm i -g vercel` permite administrar `vercel env pull`,
`vercel deploy` y `vercel logs`. Autenticarse y enlazar **el proyecto existente**.
No crear otro proyecto por accidente. Los secretos se administran en el entorno
del servidor, nunca como variables `VITE_*`.

Secuencia: copia previa, revisión de migraciones, aplicación controlada con rol
administrativo, compilación del frontend, preparación con
`node docker/api/preparar-vercel.mjs`, despliegue de revisión, verificación de
sesiones/permisos/datos y promoción. El rol limitado de Laravel no ejecuta DDL.
Nunca usar `migrate:fresh`, seeders o reimportación SQLite sobre producción.

## Estado de esta entrega

La analítica se configura localmente por instrucción del usuario. No se publicó
Metabase ni se aplicaron las tablas nuevas a Supabase en esta entrega. El sitio
de producción conserva su configuración. Para desplegar esta función hay que
completar las dependencias que siguen; no basta con subir el frontend.

## Plan de Metabase en producción

1. Disponer de un host persistente para Metabase y su planificador, con una
   versión soportada de Metabase, recursos de JVM y un dominio HTTPS. No ejecutar
   la JVM dentro de la función PHP de Vercel.
2. Crear una base PostgreSQL para el catálogo de Metabase y otra para agregados.
   Ejecutar el esquema y permisos de `docker/metricas/inicializar.sh` adaptando
   los secretos al proveedor. El lector solo recibe `SELECT`; la aplicación no
   debe usar el superusuario de PostgreSQL.
3. Revisar y aplicar la migración `2026_10_29_000100_crea_eventos_de_metrica.php`
   en el esquema privado `nexzone`, con copia previa y rol administrativo.
   Activar RLS y conceder al rol de la aplicación únicamente los permisos y
   políticas necesarios para `eventos_metrica` y `configuracion_metricas`, según
   el patrón de las tablas existentes. Verificar que los roles públicos de
   Supabase no pueden consultar estas tablas.
4. Configurar API y planificador contra la misma fuente PostgreSQL operativa.
   El host debe alcanzar los dos almacenes mediante red privada o conexiones TLS
   con reglas de acceso limitadas. No abrir PostgreSQL públicamente sin control.
5. Ejecutar `metricas:sincronizar` y `metricas:preparar` con un entorno de servidor
   privado. El usuario administrador de Metabase solo es necesario para preparar
   preguntas; retirar sus credenciales del entorno web después.
6. Mantener un scheduler persistente (`php artisan schedule:work`) o cron del
   sistema (`php artisan schedule:run` cada minuto) en ese host. El planificador
   consulta la fuente operativa real; no una copia SQLite desactualizada.
7. Publicar el frontend y backend con `METRICAS_HABILITADAS=true`, el ID del panel
   en la tabla de configuración y las variables de firma correctas.
8. Verificar HTTPS, orígenes permitidos, consentimiento, CSRF, 401/403, consultas
   de las 14 tarjetas, renovación del JWT y actualización de cinco minutos.

Variables del backend:

| Variable | Propósito |
| --- | --- |
| `METRICAS_HABILITADAS` | Activa la captura y el scheduler |
| `METRICAS_DB_HOST`, `METRICAS_DB_PORT`, `METRICAS_DB_DATABASE` | Almacén de agregados |
| `METRICAS_DB_USERNAME`, `METRICAS_DB_PASSWORD` | Usuario escritor limitado |
| `METRICAS_DB_SSLMODE` | `require` en producción; local usa `disable` en red Docker |
| `METABASE_URL` | Origen HTTPS visible al navegador |
| `METABASE_URL_INTERNA` | Dirección accesible desde el servidor |
| `METABASE_SECRET_KEY` | Misma clave de embedding que en Metabase |
| `METABASE_ADMIN_EMAIL`, `METABASE_ADMIN_PASSWORD` | Solo aprovisionamiento administrativo |
| `METRICAS_LECTOR_PASSWORD` | Solo para registrar la conexión de lectura en Metabase |

Variables de Metabase: `MB_DB_*` para su catálogo persistente,
`MB_SITE_URL`, `MB_SITE_LOCALE=es`, `MB_REPORT_TIMEZONE=America/Bogota`,
`MB_ENABLE_EMBEDDING=true`, `MB_EMBEDDING_SECRET_KEY`,
`MB_ENCRYPTION_SECRET_KEY` y `MB_EMBEDDING_APP_ORIGINS` con los orígenes concretos
de la aplicación. Desactivar telemetría opcional con
`MB_ANON_TRACKING_ENABLED=false`. Consultar los nombres y capacidades de la
versión instalada antes de publicar.

## Reversión

Para retirar la función, desactivar la captura y el scheduler, desplegar el código
anterior y conservar las tablas nuevas y el volumen durante la revisión. No hace
falta borrar datos del negocio. La reversión de la migración elimina los eventos
y el ID del dashboard; hacer copia antes de usarla.

Si falla una sincronización, el almacén conserva la instantánea anterior. Restaurar
conectividad y reejecutar el comando. Si falla Metabase, restaurar su catálogo y
clave de cifrado según [la guía de métricas](metricas.md). Para cambios de versión,
guardar copia y seguir la ruta de actualización soportada por Metabase; no hacer
downgrade de una base de catálogo migrada.

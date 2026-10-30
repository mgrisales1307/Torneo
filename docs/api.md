# API HTTP

Base: `/api/v1`. Usar `Accept: application/json`. El navegador trabaja en el mismo
origen; Vite reenvía las peticiones en desarrollo. Los cuerpos son JSON salvo
subidas de archivos, que usan `multipart/form-data`.

## Autenticación y autorización

1. `GET /sanctum/csrf-cookie` prepara la cookie de CSRF.
2. `POST /api/v1/ingreso` recibe `correo`, `clave` y `recordarme` opcional.
3. Conservar cookies e incluir `X-XSRF-TOKEN` en escrituras, usando el valor
   decodificado de `XSRF-TOKEN`. El cliente Axios existente realiza estos pasos.
4. `GET /api/v1/perfil` consulta la sesión; `POST /api/v1/salida` la cierra.

No usar tokens Bearer en el frontend ni guardar credenciales de usuario en
`localStorage`. `/panel` requiere sesión; `/admin` requiere `admin` o `empleado`.
Las políticas verifican además titularidad, capitanía y estado del torneo.
El webhook de pagos verifica su firma y no utiliza la sesión del navegador.

## Recursos

| Zona | Operaciones |
| --- | --- |
| Público | `GET /inicio`, `/juegos`, `/juegos/{juego}`, `/torneos`, `/torneos/{torneo}` |
| Competición pública | `GET /torneos/{torneo}/equipos`, `/torneos/{torneo}/cuadro`, `/partidos/{partido}` |
| Perfiles públicos | `GET /equipos/{equipo}`, `/jugadores/{apodo}` |
| Acceso | `POST /registro`, `/ingreso`, `/salida`; `GET /perfil` |
| Equipos propios | `GET/POST /panel/equipos`; `GET/PUT/PATCH/DELETE /panel/equipos/{equipo}` |
| Miembros | `POST /panel/unirse`; `PATCH/DELETE /panel/equipos/{equipo}/miembros/{usuario}` |
| Código y logo | `POST /panel/equipos/{equipo}/codigo`, `/panel/equipos/{equipo}/logo` |
| Cuentas de juego | `GET /panel/cuentas`; `PUT/DELETE /panel/cuentas/{juego}` |
| Inscripciones | `GET /panel/inscripciones`, `/panel/inscripciones/{inscripcion}`; `POST /panel/torneos/{torneo}/inscripciones`; `DELETE /panel/inscripciones/{inscripcion}` |
| Confirmación | `POST /panel/inscripciones/{inscripcion}/check-in` |
| Pagos virtuales | `POST /panel/inscripciones/{inscripcion}/orden`, `/panel/inscripciones/{inscripcion}/pagar-cartera`; `GET /panel/ordenes/{orden}` |
| Pasarela simulada | `POST /panel/ordenes/{orden}/pagar-simulado`; solo registrada al usar esa pasarela |
| Cartera | `GET /panel/cartera`; `POST /panel/cartera/retiros` |
| Partidos propios | `GET /panel/partidos`; `POST /panel/partidos/{partido}/reporte` |
| Organización | `GET /admin/resumen`, `/admin/usuarios`, `/admin/metricas` |
| Torneos administrados | `GET/POST /admin/torneos`; `GET/PUT/PATCH/DELETE /admin/torneos/{torneo}` |
| Inicio y estado | `POST /admin/torneos/{torneo}/iniciar`, `/admin/torneos/{torneo}/estado` |
| Revisión | `GET /admin/inscripciones`; `POST /admin/inscripciones/{inscripcion}/aprobar`, `/admin/inscripciones/{inscripcion}/rechazar` |
| Resultados | `GET /admin/partidos`; `PUT/PATCH /admin/partidos/{partido}`; `POST /admin/partidos/{partido}/resultado`, `/admin/partidos/{partido}/battle-royale` |
| Webhook firmado | `POST /pagos/notificaciones/{pasarela}` |

Los torneos y equipos se resuelven por slug; los jugadores por apodo y las órdenes
por referencia, según los modelos. Los cuerpos de negocio se definen en
`backend/app/Http/Requests/{Autenticacion,Panel,Admin}` y las respuestas en
`backend/app/Http/Resources`. Para conocer parámetros y filtros exactos, consultar
el controlador correspondiente y `frontend/src/lib/api.js`; no se presupone un
contrato de paginación idéntico para todos los recursos.

Inventario ejecutable:

```bash
docker compose exec api php artisan route:list --path=api/v1 --except-vendor
```

## Contrato de métricas

OpenAPI importable: [openapi-metricas.yaml](openapi-metricas.yaml). Describe los
endpoints de esta integración; no pretende ser un esquema completo del negocio.

`GET /metricas/configuracion` es público y devuelve exclusivamente
`{"habilitadas":true}` o `false`.

`POST /metricas/eventos` recibe un evento anónimo con consentimiento:

```json
{
  "id": "b0a35ab0-6d1e-4d8e-89ca-9c90e3e07347",
  "sesion": "e293313e-8a74-49d5-a2b8-9cb4e74054ee",
  "consentimiento": true,
  "tipo": "pagina",
  "pagina": "torneos",
  "dispositivo": "movil"
}
```

Tipos: `pagina`, `lcp`, `cls`, `inp`. Para rendimiento, `valor` es obligatorio,
numérico y no negativo; máximo 600000 para LCP/INP y 100 para CLS. Las páginas
permitidas están en `config/metricas.php`. Los dispositivos son `movil`, `tableta`
y `escritorio`. La fecha se asigna en el servidor.

Respuesta `204` sin cuerpo. Una visita repetida con el mismo UUID no se duplica;
una muestra de rendimiento puede actualizar su valor conservando UUID, sesión y
tipo. Con métricas desactivadas o cabeceras `DNT: 1`/`Sec-GPC: 1`, no se almacena
el evento. Los campos de transporte como consentimiento no se guardan en la tabla.

`GET /admin/metricas` devuelve `{"configurado":false}` si falta configuración o:

```json
{
  "configurado": true,
  "url": "https://metricas.example.test",
  "jwt": "TOKEN_EFIMERO",
  "actualizado_en": "2026-10-29T18:00:00+00:00"
}
```

El ID del dashboard siempre procede del servidor. La URL se configura por
entorno. El token autoriza únicamente ese dashboard durante diez minutos;
no debe registrarse ni persistirse en el navegador. `actualizado_en` puede ser
`null` antes de la primera publicación. Esta respuesta no admite caché.

## Errores y límites

| Estado | Significado |
| --- | --- |
| 401 | Sesión ausente o vencida |
| 403 | Rol o política no permite la operación |
| 404 | Recurso ausente o no visible |
| 419 | Cookie o cabecera CSRF ausente/vencida |
| 422 | Validación o regla de negocio; ver `message` y `errors` cuando existan |
| 429 | Límite de peticiones; respetar `Retry-After` |
| 500/503 | Error del servidor o dependencia; no reintentar pagos indiscriminadamente |

El límite general es 120/minuto por cuenta o IP. Hay límites específicos para
ingreso, registro, pagos, subidas, códigos de equipo y captura de métricas.
Los clientes de producción deben manejar respuestas de error sin mostrar trazas.

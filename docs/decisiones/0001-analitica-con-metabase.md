# ADR 0001 — Metabase con un almacén de agregados

- Estado: aceptada para desarrollo local.
- Fecha: 2026-10-29.
- Solicitud: integrar métricas web con Metabase; producción queda documentada.

## Contexto

El desarrollo usa SQLite y producción PostgreSQL. Conectar Metabase directamente
a la base operativa expondría información que no necesita y acoplaría los gráficos
al esquema interno. La captura de navegación debe respetar las decisiones de
privacidad del visitante.

## Decisión

Capturar eventos con consentimiento explícito y exportar agregados a una base
PostgreSQL independiente. Metabase dispone de un rol que solo puede consultar
estos agregados. Un proceso cada cinco minutos publica una instantánea completa.
El dashboard se integra mediante el componente de invitados de Metabase y JWT
firmados por Laravel, solo para `admin` y `empleado`.

Se conserva la base actual, las reglas de torneos y la autenticación existente.
La configuración de Metabase usa otra base PostgreSQL y un volumen persistente.
Preguntas y distribución del panel se aprovisionan desde código de forma repetible.

## Alternativas consideradas

- Lectura directa de producción: menos copias, pero requiere conceder acceso a
  tablas del negocio y dificulta aislar datos personales.
- Sustituir SQLite en desarrollo: no es necesario para entregar analítica.
- Dashboard propio con datos ficticios: no satisface la integración solicitada.

## Consecuencias

Se añaden Metabase, PostgreSQL y un planificador local. Hay un retraso esperado
de hasta cinco minutos y una ventana de 90 días. Las visitas medidas no equivalen
a todo el tráfico. No se presenta un inventario de estados como conversión ni
se promedian percentiles diarios para inventar un percentil mensual.

La puesta en producción requiere alojamiento persistente para Metabase y una
versión soportada verificada, TLS, copias, conectividad y ejecución programada;
no se intenta ejecutar la JVM de Metabase dentro de la función PHP de Vercel.

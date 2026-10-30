# Historial de cambios

## Cambios en main — 2026-10-29 (sin versión etiquetada)

### Añadido

- Accesos visibles a Organización y Métricas desde Mi panel para administrador
  y empleado, conservando la navegación habitual del jugador.

- Analítica de visitas y rendimiento con consentimiento, DNT/GPC y rutas sin identidad.
- Almacén PostgreSQL de agregados, lector de solo consulta y sincronización cada cinco minutos.
- Metabase local persistente, aprovisionamiento reproducible de 14 tarjetas y
  dashboard integrado en Organización con tokens de diez minutos.
- Indicadores de tráfico, dispositivos, Core Web Vitals, registros, inscripciones,
  torneos, cupos y pagos exclusivamente virtuales.
- Documentación de arquitectura, API, OpenAPI de métricas, instalación, despliegue,
  seguridad, pruebas, contribución y decisiones; plantillas de GitHub y CI.
- Pruebas de privacidad, permisos, deduplicación, sesiones, firma y agregación.
- Correcciones compatibles de dependencias del frontend detectadas por auditoría;
  `npm audit` termina sin vulnerabilidades conocidas en esta verificación.

### Alcance operativo

Configuración local. Metabase de producción y migraciones en Supabase pendientes
de seguir el procedimiento documentado. Se registran las versiones realmente
comprobadas y el fallo de red al descargar imágenes nuevas en la guía de métricas.

### Incidencia conocida

El dashboard se comprobó en el navegador integrado, pero su marco queda vacío
en Chrome. El acceso directo a Metabase está disponible; la causa y la validación
visual completa en Chrome siguen pendientes. El error de ruta tras actualizar
Git se resolvió reiniciando el servidor Vite.

## Estado previo

Plataforma de esports con cinco juegos, cinco formatos de competición,
inscripciones individuales o por equipos, carteras virtuales y publicación de
la aplicación en Vercel/Supabase. El historial anterior permanece en Git y en el README.

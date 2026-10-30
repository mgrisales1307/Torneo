# Documentación de NexZone

Punto de entrada: [README del proyecto](../README.md). La implementación es la
fuente de verdad; esta documentación explica sus contratos y su operación.

| Documento | Qué resuelve |
| --- | --- |
| [Arquitectura](arquitectura.md) | Componentes, datos, permisos e invariantes del dominio |
| [API](api.md) | Rutas, autenticación, errores y contrato de métricas |
| [Métricas y Metabase](metricas.md) | Instalación, indicadores, privacidad, operación y recuperación |
| [Despliegue](despliegue.md) | Aplicación en Vercel/Supabase y plan de Metabase en producción |
| [Pruebas](pruebas.md) | Comandos, cobertura, comprobación manual y criterios de entrega |
| [Decisión de analítica](decisiones/0001-analitica-con-metabase.md) | Motivos, alternativas y límites del almacén agregado |
| [OpenAPI de métricas](openapi-metricas.yaml) | Contrato importable de los endpoints nuevos |
| [Contribución](../CONTRIBUTING.md) | Convenciones, ramas, PR y cambios de esquema |
| [Seguridad](../SECURITY.md) | Secretos, permisos, privacidad y reporte de fallos |
| [Historial](../CHANGELOG.md) | Cambios entregados y límites conocidos |

El código incluye comentarios en las fronteras que requieren contexto: privacidad,
agregación, firma del panel, persistencia transaccional y aprovisionamiento.
Las pruebas ejecutables describen los contratos observables.

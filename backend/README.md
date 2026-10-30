# API de NexZone

Laravel 13 y Sanctum; servicios del dominio en español. SQLite en desarrollo y
PostgreSQL de Supabase en producción. El [README general](../README.md) explica
el arranque, las cuentas locales y las reglas de competición.

- [Arquitectura e invariantes](../docs/arquitectura.md)
- [API y autenticación](../docs/api.md)
- [Métricas y Metabase](../docs/metricas.md)
- [Pruebas](../docs/pruebas.md)
- [Despliegue y migraciones](../docs/despliegue.md)
- [Seguridad](../SECURITY.md)

Desde la raíz del repositorio:

```bash
docker compose up -d
docker compose exec api php artisan test
docker compose exec api php artisan route:list --path=api/v1
```

Los controladores coordinan HTTP, los Form Requests validan y las políticas
autorizan. Las transacciones y las máquinas de estado viven en los servicios.
`metricas:sincronizar` y `metricas:preparar` requieren el entorno Compose de
métricas descrito en su guía. No ejecutar seeders ni `migrate:fresh` en producción.

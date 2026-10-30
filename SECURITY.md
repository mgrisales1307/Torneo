# Seguridad

## Reportar una vulnerabilidad

No publiques secretos ni datos de usuarios en un issue. Si GitHub ofrece
**Security → Report a vulnerability** para este repositorio, utiliza ese canal.
Si no aparece, contacta al mantenedor por un canal privado acordado; no se
presupone una dirección de correo de seguridad que no está configurada.

Incluye versiones, entorno, pasos mínimos, efecto y evidencia anonimizada.
No pruebes explotación contra el sitio publicado sin autorización.

## Controles del proyecto

- Sanctum con cookies, CSRF y autorización de rol/políticas en Laravel.
- Secretos en entorno del servidor; jamás en Git, capturas o variables `VITE_*`.
- RLS y rol limitado en el esquema privado de Supabase en producción.
- Operaciones de dinero virtual transaccionales e idempotentes.
- Metabase solo consulta agregados; su lector no puede modificar datos.
- Clave de firma solo en servidor; tokens acotados al dashboard y diez minutos.
- Consentimiento y DNT/GPC para navegación; sin identidades en el almacén agregado.
- Metabase local ligado a `127.0.0.1`; PostgreSQL sin puerto expuesto al host.

Las cuentas de demostración del README son exclusivamente locales. Antes de
publicar, eliminar o cambiar credenciales de demostración, comprobar HTTPS,
`APP_DEBUG=false`, cookies seguras, origen permitido y copias restaurables.

## Dependencias e incidentes

Revisar `composer audit` y `npm audit` al actualizar paquetes. No ejecutar
actualizaciones mayores automáticas sin comprobar compatibilidad y pruebas.
No considerar segura una versión solo porque una herramienta no produjo alertas.

Si se filtra una clave, revocarla/rotarla en el servicio, actualizar el entorno
y revisar el alcance. Quitarla del último commit no la elimina del historial.
Para la clave de embedding, la rotación invalida tokens existentes; coordinar API
y Metabase. Para el cifrado del catálogo, conservar la clave al restaurar.

La telemetría web puede ser manipulada por clientes: no es una fuente de
autorización, facturación ni antifraude. Logs del servidor y herramientas de
infraestructura tienen su propia política de privacidad y conservación.

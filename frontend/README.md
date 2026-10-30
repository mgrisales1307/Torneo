# Interfaz de NexZone

React 19 + Vite, React Router, TanStack Query, Axios y Lucide. El diseño y los
nombres de la aplicación están en español. Fuentes y recursos se sirven localmente.

Con la API de Docker encendida, desde esta carpeta:

```bash
npm ci
npm run dev
```

Abrir `http://localhost:5173`. El proxy reenvía `/api`, `/sanctum` y `/storage`;
no se requiere una URL de API en variables `VITE_*`. Cookies y CSRF se gestionan
en `src/lib/api.js`.

```bash
npm test
npm run lint
npm run build
```

Pantallas en `src/paginas/{publico,acceso,panel,admin}` y piezas reutilizables en
`src/componentes`. `App.jsx` declara las rutas; la autorización real se comprueba
en Laravel.

`MetricasDeNavegacion` solicita permiso y observa rutas sin enviar identidades.
`web-vitals` y el componente Metabase se cargan bajo demanda. La pantalla
`/organizacion/metricas` obtiene tokens efímeros del servidor, nunca claves.
Configurar el servicio con [la guía de métricas](../docs/metricas.md).

Más información: [arquitectura](../docs/arquitectura.md), [API](../docs/api.md),
[pruebas](../docs/pruebas.md), [contribución](../CONTRIBUTING.md) y
[documentación completa](../docs/README.md).

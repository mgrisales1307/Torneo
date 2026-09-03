# Juegos Zone — Torneos

## Requisitos

- Docker Desktop
- PHP 8.3 o superior y Composer
- Node.js y npm

## Primera instalación

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
docker compose up -d --build
```

El contenedor crea la base SQLite, ejecuta las migraciones y carga los usuarios
de desarrollo automáticamente. También corrige en cada arranque los permisos de
`storage`, `bootstrap/cache` y `database`, independientemente del UID/GID del
equipo que clonó el repositorio.

La API queda disponible en <http://localhost>.

### Frontend

En otra terminal:

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

El frontend queda disponible en <http://localhost:5173>.

## Credenciales de desarrollo

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Usuario | `test@example.com` | `password` |
| Administrador | `admin@juegoszone.com` | `CambiaEstaClave123` |

Estas credenciales son exclusivamente para desarrollo local.

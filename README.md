# Juegos Zone — Torneos

Aplicación de gestión de torneos.

- **backend/** — API en Laravel 13 con autenticación por Sanctum (cookies de sesión), servida en Docker.
- **frontend/** — SPA en React 19 + Vite.

---

## Requisitos

| Herramienta | Versión | Notas |
| --- | --- | --- |
| **Docker Desktop** | actual | Debe estar **abierto y corriendo** antes de empezar. |
| **Node.js** | 20.19 o superior | Compruébalo con `node -v`. |
| **Git** | cualquiera | |

PHP y Composer **no son obligatorios**: si no los tienes, la instalación los ejecuta dentro de un contenedor temporal.

En **Windows**: usa la terminal de **WSL (Ubuntu)** o **Git Bash** en VS Code. En PowerShell puro, sigue la instalación manual de más abajo.

---

## Instalación (primera vez)

```bash
git clone https://github.com/mgrisales1307/Torneo.git
cd Torneo
./setup.sh
```

El script se encarga de todo: crea los archivos `.env`, instala las dependencias de PHP, genera la `APP_KEY`, construye y levanta el contenedor, espera a que la API responda, y instala las dependencias de Node.

Cuando termine, arranca el frontend:

```bash
cd frontend && npm run dev
```

Abre <http://localhost:5173>.

---

## Uso diario

Una vez instalado, para trabajar solo necesitas dos comandos:

```bash
cd backend && docker compose up -d
```

```bash
cd frontend && npm run dev
```

Para apagar el backend al terminar:

```bash
cd backend && docker compose down
```

---

## Credenciales de desarrollo

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | `admin@juegoszone.com` | `CambiaEstaClave123` |
| Usuario | `test@example.com` | `password` |

Se crean solas al levantar el contenedor. Son **exclusivamente para desarrollo local**.

---

## Instalación manual

Si prefieres no usar `setup.sh` (o estás en PowerShell), estos son los mismos pasos, uno a uno.

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
docker compose up -d --build
```

Sin PHP/Composer locales, reemplaza los dos comandos del medio por:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 php artisan key:generate
```

La API queda en <http://localhost>. Verifica con <http://localhost/up>.

Al arrancar, el contenedor crea la base SQLite, ejecuta las migraciones, carga los usuarios de desarrollo y corrige los permisos de `storage`, `bootstrap/cache` y `database` — sin importar el UID/GID del equipo que clonó el repositorio.

### Frontend

En otra terminal:

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

Queda en <http://localhost:5173>.

---

## Arquitectura

El frontend autentica con **cookies de sesión**, no con tokens en `localStorage`. El flujo es:

1. `GET /sanctum/csrf-cookie` deja la cookie `XSRF-TOKEN`.
2. `POST /api/login` con esa cookie abre la sesión.
3. Las peticiones siguientes viajan con `withCredentials`.

Por eso los orígenes tienen que coincidir exactamente. Si cambias un puerto, actualiza **los tres** valores:

| Archivo | Variable | Valor por defecto |
| --- | --- | --- |
| `backend/.env` | `APP_PORT` | `80` |
| `backend/.env` | `FRONTEND_URL` | `http://localhost:5173` |
| `backend/.env` | `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,127.0.0.1:5173` |
| `frontend/.env.local` | `VITE_API_URL` | `http://localhost` |

Usa siempre `localhost` en el navegador, no `127.0.0.1`: mezclarlos rompe las cookies de sesión.

Tras editar `backend/.env`, recarga la configuración:

```bash
cd backend && docker compose restart laravel.test
```

---

## Problemas frecuentes

**`port is already allocated` al levantar Docker**
El puerto 80 está ocupado. En `backend/.env` añade `APP_PORT=8080`, pon `VITE_API_URL=http://localhost:8080` en `frontend/.env.local`, y vuelve a levantar con `docker compose up -d`.

**`No application encryption key has been specified`**
Falta la `APP_KEY`. Ejecútalo y reinicia el contenedor:
```bash
cd backend && docker compose exec laravel.test php artisan key:generate
```

**El login responde 419 (CSRF token mismatch)**
Los orígenes no coinciden. Revisa la tabla de arriba y asegúrate de entrar por `localhost`, no por `127.0.0.1`.

**El login responde 401 o "No pudimos conectar con el servidor"**
Comprueba que la API está viva: <http://localhost/up> debe devolver una página. Si no, mira los registros:
```bash
cd backend && docker compose logs -f laravel.test
```

**`docker compose up` falla con `vendor/laravel/sail/runtimes/8.5: no such file`**
Falta `composer install`. La imagen de Docker se construye desde `vendor/`, así que las dependencias de PHP tienen que instalarse **antes** de levantar el contenedor.

**`bash\r: command not found` o `docker/start-dev.sh: bad interpreter`**
Finales de línea de Windows. El `.gitattributes` del repositorio lo previene, pero si ya te pasó, corrige el script y vuelve a levantar:
```bash
sed -i 's/\r$//' backend/docker/start-dev.sh
cd backend && docker compose up -d --force-recreate
```

**Errores de permisos en `storage/` o `database/`**
Reconstruye el contenedor, que corrige los permisos al arrancar:
```bash
cd backend && docker compose down && docker compose up -d --build
```

**Quiero empezar de cero con la base de datos**
```bash
cd backend && docker compose exec laravel.test php artisan migrate:fresh --seed
```

---

## Pruebas

```bash
cd backend && docker compose exec laravel.test php artisan test
```

# Juegos Zone — Torneos

Aplicación de gestión de torneos.

- **backend/** — API en Laravel 13 con autenticación por Sanctum. Corre en Docker.
- **frontend/** — SPA en React 19 + Vite.

---

## Requisitos

Solo dos cosas:

- **Docker Desktop**, abierto y corriendo.
- **Node.js 20.19 o superior** (`node -v`).

PHP, Composer y las extensiones de PHP **no hacen falta**: van dentro de la imagen de Docker.

---

## Cómo se arranca

Dos comandos, en dos terminales. La primera vez, el paso 1 tarda unos minutos
mientras Docker construye la imagen e instala las dependencias.

**Terminal 1 — la API:**

```bash
docker compose up
```

**Terminal 2 — el frontend:**

```bash
cd frontend && npm install && npm run dev
```

Abre <http://localhost:5173>.

Eso es todo. No hay que copiar archivos `.env`, ni generar claves, ni correr
migraciones, ni instalar dependencias de PHP: el contenedor lo hace solo al
arrancar, y lo vuelve a comprobar cada vez.

### Credenciales de desarrollo

| Rol | Correo | Contraseña | Puede |
| --- | --- | --- | --- |
| `admin` | `admin@juegoszone.com` | `admin123` | Todo, incluida la gestión de usuarios |
| `empleado` | `empleado@juegoszone.com` | `trabajador123` | Organizar el torneo y ver los usuarios |
| `user` | `test@example.com` | `password` | Participar; no accede a la gestión |

Se crean solas al levantar el contenedor, y volver a ejecutar los seeders no
las duplica. Son **exclusivamente para desarrollo local**.

Para crearlas de nuevo sin tocar el resto de los datos:

```bash
docker compose exec api php artisan db:seed
```

---

## Uso diario

Para dejar la API corriendo en segundo plano y recuperar la terminal:

```bash
docker compose up -d
```

Apagarla al terminar:

```bash
docker compose down
```

Ver qué está haciendo:

```bash
docker compose logs -f api
```

---

## Cómo está armado

El navegador habla **con un solo origen**: `http://localhost:5173`. Vite reenvía
por detrás las rutas `/api` y `/sanctum` al contenedor de la API.

```
navegador  ──►  Vite (5173)  ──►  Docker: Laravel (8000)
                     └── sirve la SPA
```

Esto no es un detalle de implementación, es lo que evita la mayoría de los
errores: al no haber dos dominios, no hay CORS que configurar, ni cookies de
sesión que se pierdan entre orígenes, ni URLs de API que se queden
desactualizadas en un archivo `.env`. Por eso el frontend no tiene ninguna
variable de entorno que configurar.

Los valores que la API necesita para reconocer al frontend están fijados en
[`compose.yaml`](compose.yaml), no en `backend/.env`, porque las variables de
entorno del contenedor tienen prioridad. Así no pueden quedar desalineados.

### Estructura

```
compose.yaml          Levanta la API. Es el único archivo que hay que ejecutar.
docker/api/           Imagen de la API: trae PHP, Composer y el arranque.
backend/              Laravel. Su .env se genera solo la primera vez.
frontend/             React + Vite.
```

### Si el puerto 8000 está ocupado

```bash
API_PORT=8010 docker compose up
```

Y en la otra terminal, para que Vite sepa a dónde reenviar:

```bash
cd frontend && API_PORT=8010 npm run dev
```

---

## Comandos del backend

Se ejecutan dentro del contenedor, con la API levantada:

```bash
docker compose exec api php artisan test
```

```bash
docker compose exec api php artisan migrate:fresh --seed
```

```bash
docker compose exec api php artisan route:list
```

Para una terminal dentro del contenedor:

```bash
docker compose exec api bash
```

---

## Problemas

**`Cannot connect to the Docker daemon`**
Docker Desktop no está corriendo. Ábrelo y espera a que termine de arrancar.

**`port is already allocated`**
Algo ocupa el 8000. Levanta con otro puerto, como se explica arriba.

**`Port 5173 is already in use`**
Quedó vivo un `npm run dev` de antes. Encuéntralo y ciérralo:
```bash
lsof -i :5173
```
Vite falla a propósito en vez de cambiarse de puerto en silencio: el backend
espera el 5173, y un cambio de puerto rompería el login más adelante.

**`npm install` tarda minutos, o Vite arranca sin leer `vite.config.js`**
Se está usando el Node de **Windows** sobre la carpeta de WSL. Compruébalo
desde la terminal de Ubuntu:
```bash
which -a node npm
```
Si alguna línea empieza por `/mnt/c/`, ese es el problema: los archivos viajan
por el recurso de red `\\wsl.localhost`, y npm instala los binarios nativos
de Windows, que en Linux no sirven. Instala Node dentro de Ubuntu:
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
exec bash
nvm install 22
```
Y reinstala limpio, porque lo que hay quedó con los binarios equivocados:
```bash
cd frontend && rm -rf node_modules && npm install
```
Señales de que es esto: `npm install` que tarda minutos, un mensaje sobre
`CMD.EXE` y rutas UNC, o que Vite diga `Network: use --host to expose` (indica
que no leyó `vite.config.js`, así que tampoco tiene el proxy hacia la API).

**La página no carga en `localhost:5173`**
Mira la línea `➜  Local:` de la terminal de Vite: ahí está la dirección real.
Si el proceso ya volvió al prompt, es que murió — el error está justo encima.

**El navegador de Windows no alcanza a `localhost` aunque el proceso corra en WSL**
Se rompió el reenvío de puertos de WSL2. Cierra las terminales de Ubuntu y, en
PowerShell:
```powershell
wsl --shutdown
```
Espera unos segundos y vuelve a abrir Ubuntu.

**Cambié algo en `backend/.env` y no surte efecto**
Reinicia el contenedor:
```bash
docker compose restart api
```
Ten en cuenta que las variables definidas en `compose.yaml` (`APP_URL`,
`FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `DB_CONNECTION`) tienen prioridad
sobre `backend/.env` a propósito.

**Quiero empezar de cero**
Borra la base y las dependencias, y vuelve a levantar:
```bash
docker compose down
rm -rf backend/vendor backend/.env backend/database/database.sqlite
docker compose up
```

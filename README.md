# Juegos Zone — Torneos

Plataforma de torneos de fútbol. Cualquiera puede seguir la competencia sin
cuenta: tabla de posiciones, calendario, resultados, plantillas y goleadores.
Los dueños de equipo se registran, inscriben su equipo pagando la inscripción y
gestionan su plantilla. La organización crea los torneos, revisa inscripciones,
genera el calendario y carga los resultados.

- **backend/** — API en Laravel 13 con autenticación por Sanctum (sesión en cookie).
- **frontend/** — SPA en React 19 + Vite, con React Router y TanStack Query.

---

## Requisitos

Solo dos cosas:

- **Docker Desktop**, abierto y corriendo.
- **Node.js 20.19 o superior** (`node -v`).

PHP, Composer y las extensiones de PHP **no hacen falta**: van dentro de la
imagen de Docker.

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

No hay que copiar archivos `.env`, ni generar claves, ni correr migraciones: el
contenedor lo hace solo al arrancar, y lo vuelve a comprobar cada vez.

### Si ya tenías el proyecto de antes

`docker compose up` aplica las migraciones nuevas sobre tu base de datos
actual, sin borrar nada. Si prefieres empezar de cero con los datos de
demostración:

```bash
docker compose exec api php artisan migrate:fresh --seed
```

### Credenciales de desarrollo

| Rol | Correo | Contraseña | Puede |
| --- | --- | --- | --- |
| `admin` | `admin@juegoszone.com` | `admin123` | Todo, incluida la gestión de usuarios |
| `empleado` | `empleado@juegoszone.com` | `trabajador123` | Organizar torneos, inscripciones y resultados |
| `dueno` | `dueno@juegoszone.com` | `dueno123` | Sus equipos, su plantilla y sus pagos |
| `espectador` | `test@example.com` | `password` | Solo mirar; no accede a ningún panel |

Hay dos dueños más (`dueno2@` y `dueno3@juegoszone.com`, misma contraseña) para
probar que nadie puede tocar el equipo de otro.

Son **exclusivamente para desarrollo local**. `admin` y `empleado` se aseguran
en cada arranque, porque sin ellas nadie puede entrar al panel de organización.
Las demás —los tres dueños y el espectador— solo se crean en una base de datos
recién nacida.

### Datos de demostración

La primera vez que arranca, la base se llena con un torneo completo: ocho
equipos con plantilla, calendario generado y las primeras cuatro jornadas ya
jugadas, más un segundo torneo con las inscripciones abiertas para probar el
flujo de pago.

Se siembra **solo en una base recién creada**. Esto importa: el arranque del
contenedor ejecuta `migrate --seed` cada vez, así que si la condición fuera
más laxa, un reinicio le devolvería los datos de prueba a quien acaba de
borrarlos, y hasta le crearía equipos de demostración a nombre de su propia
cuenta.

Para vaciar la base y volver a empezar con la demostración:

```bash
docker compose exec api php artisan migrate:fresh --seed
```

Y para trabajar con la base limpia, sin datos de prueba, basta borrar lo que
sobre: los reinicios respetan ese estado y solo se aseguran de que existan
`admin` y `empleado`.

---

## Cómo está armado

El navegador habla **con un solo origen**: `http://localhost:5173`. Vite reenvía
por detrás `/api`, `/sanctum` y `/storage` al contenedor de la API.

```
navegador  ──►  Vite (5173)  ──►  Docker: Laravel (8000)
                     └── sirve la SPA
```

Esto no es un detalle de implementación, es lo que evita la mayoría de los
errores: al no haber dos dominios, no hay CORS que configurar, ni cookies de
sesión que se pierdan entre orígenes, ni URLs de API que se queden
desactualizadas en un archivo `.env`.

Los valores que la API necesita para reconocer al frontend están fijados en
[`compose.yaml`](compose.yaml), no en `backend/.env`, porque las variables de
entorno del contenedor tienen prioridad.

### Tipografía

Dos familias, las dos alojadas en el propio proyecto (paquetes de Fontsource en
`node_modules`), nunca pedidas a Google Fonts: así la aplicación no depende de
un tercero y no se filtra la IP de quien nos visita.

| Voz | Familia | Dónde |
| --- | --- | --- |
| Titulares | **Instrument Serif** | Portadas, nombres de torneo, títulos de sección |
| Interfaz | **Archivo** | Todo el texto, botones, formularios |
| Datos | **Archivo estrechada** (clase `.datos`) | Tablas, marcadores, minutos, dorsales |

La tercera voz no es una tercera fuente: Archivo es variable y trae un **eje de
ancho** (62 %–125 %), así que la misma familia se estrecha para las columnas de
cifras, como se compone una tabla de posiciones en un periódico deportivo.

Dos detalles que conviene no deshacer:

- Las cifras de las tablas llevan `font-variant-numeric: tabular-nums`. Hace
  falta de verdad: los dígitos de Archivo miden 575, 576 o 577 unidades según
  el caso, y sin esa propiedad las columnas se desalinean fila a fila.
- Los pesos son los de la escala (400, 500, 600, 700) y el interletraje
  negativo se reserva para los titulares grandes. Valores intermedios del tipo
  `font-weight: 730` o tracking cerrado en el texto corrido son lo que le da a
  una interfaz ese aire de plantilla generada.

### Convención de nombres

**Todo el código del proyecto está en español**: clases, métodos, tablas,
columnas, rutas y mensajes. Las únicas excepciones son las que el framework fija
por su cuenta —las carpetas de Laravel (`app/Models`, `app/Http`), las columnas
`created_at` / `updated_at` / `remember_token`, y el prefijo `use` que React
exige en los hooks—. Si al leer un archivo aparece una palabra en inglés que no
esté en esa lista, es un descuido.

### Estructura

```
compose.yaml                Levanta la API. Es el único archivo que hay que ejecutar.
docker/api/                 Imagen de la API: PHP, Composer y el arranque.

backend/
  app/Enumeraciones/        Estados y tipos del dominio (torneo, inscripción, partido…).
  app/Models/               Torneo, Equipo, Jugador, Inscripcion, Orden, Pago, Partido…
  app/Policies/             Quién puede hacer qué sobre cada modelo.
  app/Servicios/
    Torneos/                Calendario, tabla de posiciones, goleadores, resultados.
    Inscripciones/          Transiciones de estado de una inscripción.
    Pagos/                  Contrato de pasarela, pasarela simulada y gestor de cobros.
    Medios/                 Subida y validación de escudos, uniformes y fotos.
  app/Http/Controllers/Api/V1/
    Publico/                Lo que ve cualquier visitante.
    Dueno/                  El panel del dueño de equipo.
    Admin/                  El panel de la organización.
  app/Http/Requests/        Validación de cada formulario.
  app/Http/Resources/       Forma del JSON que sale de la API.
  lang/es/                  Mensajes de validación en español.
  tests/Feature/            100 pruebas que cubren el flujo completo.

frontend/src/
  paginas/publico/          Portada, torneos, ficha de equipo, detalle de partido.
  paginas/acceso/           Ingreso y registro.
  paginas/panel/            Equipos, plantilla, inscripciones y pago.
  paginas/admin/            Resumen, torneos, calendario, resultados, usuarios.
  componentes/ui/           Botones, campos, tarjetas, diálogos, insignias.
  componentes/torneo/       Tabla de posiciones, lista de partidos, goleadores.
  componentes/diseno/       Cabecera, pie, armazón del sitio y rutas protegidas.
  contexto/                 La sesión de quien está navegando.
  lib/                      Cliente de la API y formato de fechas y dinero.
  estilos/                  Tokens de diseño, base, componentes y páginas.
```

---

## Cómo funciona el torneo

### El recorrido de un equipo

```
  registro  →  crear equipo  →  cargar plantilla  →  inscribirse  →  pagar
                                                                       │
        la organización revisa  ◄──────────────────────────────────────┘
                   │
                   ├─ aprueba  →  el equipo entra al calendario y a la tabla
                   └─ rechaza  →  el dueño ve el motivo y puede corregir
```

Una inscripción pasa por estos estados: `pendiente_pago` → `pendiente_revision`
→ `aprobada`, y puede terminar en `rechazada` o `retirada`. El estado no se
cambia a mano en ningún controlador: lo mueve `GestorDeInscripciones`, que es
quien conoce las transiciones válidas.

### El calendario

`GeneradorDeCalendario` arma la liga con el método del círculo: con n equipos
salen n-1 jornadas donde todos juegan contra todos una vez, alternando local y
visitante. Si el número de equipos es impar, uno descansa cada fecha. En el
formato de ida y vuelta se duplican las jornadas invirtiendo la localía.

### La tabla de posiciones

No se guarda en ninguna columna: se calcula siempre a partir de los partidos
finalizados, así que es imposible que contradiga a los resultados. Para que eso
no cueste una consulta pesada en cada visita, se guarda en caché y los servicios
que tocan resultados la invalidan.

El marcador de un partido tiene que cuadrar con los goles registrados —contando
los autogoles al revés—, o no se guarda nada. Es lo que evita que la tabla y la
de goleadores cuenten historias distintas del mismo partido.

### Los pagos

La pasarela está detrás de una interfaz (`PasarelaDePagos`), y la que viene
configurada es `PasarelaSimulada`: hace el mismo recorrido que un proveedor real
—abrir el cobro, notificar el resultado firmado, verificar esa firma— sin salir
del proyecto, así que el flujo se puede probar y demostrar sin credenciales de
nadie.

Integrar Wompi, Mercado Pago, Stripe o PayU es escribir una clase que implemente
esa interfaz y registrarla en `config/pagos.php`. Ni los controladores, ni los
modelos, ni el frontend cambian.

Reglas que se cumplen siempre, y que están cubiertas por pruebas:

- El monto sale de la inscripción, nunca de lo que mande el navegador.
- Las notificaciones vienen firmadas con HMAC-SHA256 y se comparan en tiempo
  constante, con una ventana de tiempo para que una notificación capturada no se
  pueda reenviar después.
- Procesar dos veces la misma notificación no cobra dos veces: hay un índice
  único por transacción.
- El dinero se guarda en centavos, como entero. Nunca en coma flotante.

---

## Seguridad

Decisiones que ya están tomadas en el código:

- **La sesión va en una cookie HttpOnly** firmada por Laravel, con protección
  CSRF. No hay ningún token en `localStorage`: lo que el JavaScript no puede
  leer, un XSS tampoco se lo lleva.
- **Dos capas de permisos.** El middleware `rol` corta por rol a la entrada de
  cada zona; las políticas (`app/Policies`) deciden pieza por pieza si algo es
  tuyo. Cambiar un identificador en la URL devuelve 403, no los datos de otro.
- **Nada de asignación masiva a ciegas.** Estados, montos y marcadores no son
  `fillable`: los escriben los servicios que conocen las reglas.
- **Las imágenes se validan de verdad** —tipo real del archivo, peso y
  dimensiones— y se guardan con un nombre generado por el servidor. SVG no se
  acepta: es XML y puede traer scripts dentro.
- **Límite de peticiones** en ingreso (por correo e IP), registro, pagos,
  subidas y la API en general.
- **Mensajes que no filtran**: un correo inexistente y una contraseña
  equivocada responden exactamente lo mismo.
- **Cabeceras de seguridad** (`nosniff`, `DENY`, `Referrer-Policy`) en toda
  respuesta.

---

## Uso diario

Dejar la API en segundo plano y recuperar la terminal:

```bash
docker compose up -d
```

Apagarla:

```bash
docker compose down
```

Ver qué está haciendo:

```bash
docker compose logs -f api
```

### Comandos del backend

Se ejecutan dentro del contenedor, con la API levantada:

```bash
docker compose exec api php artisan test
```

```bash
docker compose exec api php artisan migrate:fresh --seed
```

```bash
docker compose exec api php artisan route:list --except-vendor
```

Para una terminal dentro del contenedor:

```bash
docker compose exec api bash
```

### Comandos del frontend

```bash
cd frontend && npm test
```

```bash
cd frontend && npm run lint
```

```bash
cd frontend && npm run build
```

Las pruebas del frontend viven en `src/pruebas` y corren con Vitest sobre un
DOM simulado. Son pocas y van al grano: cubren el comportamiento que no se ve
en el código pero se sufre al usarlo —por ejemplo, que un campo dentro de un
modal deje escribir seguido sin perder el foco—.

---

## La API

Todo cuelga de `/api/v1`. Las tres zonas son:

| Zona | Prefijo | Quién entra |
| --- | --- | --- |
| Pública | `/api/v1/…` | Cualquiera, sin cuenta |
| Dueño de equipo | `/api/v1/panel/…` | `dueno`, `admin`, `empleado` |
| Organización | `/api/v1/admin/…` | `admin`, `empleado` |

Las rutas principales:

```
GET    /api/v1/inicio                           Portada: torneos, próximos partidos, resultados
GET    /api/v1/torneos                          Listado de torneos publicados
GET    /api/v1/torneos/{slug}                   Ficha del torneo
GET    /api/v1/torneos/{slug}/tabla             Tabla de posiciones
GET    /api/v1/torneos/{slug}/partidos          Calendario y resultados
GET    /api/v1/torneos/{slug}/goleadores        Tabla de goleadores
GET    /api/v1/torneos/{slug}/equipos           Equipos que ya están dentro
GET    /api/v1/equipos/{slug}                   Ficha del equipo y su plantilla
GET    /api/v1/partidos/{id}                    Detalle del partido con sus goles

POST   /api/v1/registro                         Crear cuenta de dueño
POST   /api/v1/ingreso                          Entrar
POST   /api/v1/salida                           Salir
GET    /api/v1/perfil                           Quién soy

GET    /api/v1/panel/equipos                    Mis equipos
POST   /api/v1/panel/equipos                    Crear equipo
POST   /api/v1/panel/equipos/{slug}/imagenes    Escudo y uniformes
POST   /api/v1/panel/equipos/{slug}/jugadores   Sumar un jugador
POST   /api/v1/panel/torneos/{slug}/inscripciones   Inscribir mi equipo
POST   /api/v1/panel/inscripciones/{id}/orden       Generar la orden de pago

GET    /api/v1/admin/resumen                    Cifras y pendientes
POST   /api/v1/admin/torneos                    Crear torneo
POST   /api/v1/admin/torneos/{slug}/calendario  Generar el calendario
POST   /api/v1/admin/inscripciones/{id}/aprobar Aprobar una inscripción
POST   /api/v1/admin/partidos/{id}/resultado    Cargar el resultado
```

La lista completa: `docker compose exec api php artisan route:list --except-vendor`.

---

## Lo que falta para la próxima etapa

La estructura ya está lista para estas piezas, que hoy no existen:

- **Fase de grupos y eliminación directa.** El enum `FormatoTorneo` ya tiene el
  caso `EliminacionDirecta` y la tabla de inscripciones tiene columna `grupo`;
  falta que `GeneradorDeCalendario` sepa construir esos cuadros.
- **Pasarela de pago real.** Implementar `PasarelaDePagos` con el proveedor que
  se elija y cambiar `PASARELA_PAGOS` en el entorno.
- **Notificaciones por correo** cuando se aprueba o se rechaza una inscripción
  (`Usuario` ya es `Notifiable` y sabe a qué correo escribir).
- **Sanciones y suspensiones automáticas** a partir de las tarjetas, que ya se
  registran partido a partido.
- **Bitácora de auditoría** de las acciones de la organización.

---

## Problemas

**`Cannot connect to the Docker daemon`**
Docker Desktop no está corriendo. Ábrelo y espera a que termine de arrancar.

**`port is already allocated`**
Algo ocupa el 8000. Levanta con otro puerto:

```bash
API_PORT=8010 docker compose up
```

Y en la otra terminal, para que Vite sepa a dónde reenviar:

```bash
cd frontend && API_PORT=8010 npm run dev
```

**`Port 5173 is already in use`**
Quedó vivo un `npm run dev` de antes. Encuéntralo y ciérralo:

```bash
lsof -i :5173
```

Vite falla a propósito en vez de cambiarse de puerto en silencio: el backend
espera el 5173, y un cambio de puerto rompería el login.

**Los escudos y las fotos no se ven (salen como imagen rota, o dan 403)**
El enlace `backend/public/storage` está roto. Pasa cuando se crea desde el host
—por ejemplo al correr `php artisan storage:link` fuera de Docker—, porque
entonces apunta a una ruta absoluta del host que dentro del contenedor no
existe; y también al revés. El arranque del contenedor lo rehace en cada
inicio como enlace *relativo*, que sí vale en los dos sitios, así que basta
reiniciar:

```bash
docker compose restart api
```

Si prefieres arreglarlo sin reiniciar, desde la carpeta del proyecto:

```bash
ln -sfn ../storage/app/public backend/public/storage
```

Para comprobar que quedó bien, el enlace tiene que verse relativo:

```bash
ls -l backend/public/storage
```

Debe decir `storage -> ../storage/app/public`. Si dice una ruta que empieza por
`/home/...` o `/var/www/...`, está mal.

**`npm install` tarda minutos, o Vite arranca sin leer `vite.config.js`**
Se está usando el Node de **Windows** sobre la carpeta de WSL. Compruébalo
desde la terminal de Ubuntu:

```bash
which -a node npm
```

Si alguna línea empieza por `/mnt/c/`, ese es el problema: los archivos viajan
por el recurso de red `\\wsl.localhost`, y npm instala los binarios nativos de
Windows, que en Linux no sirven. Instala Node dentro de Ubuntu:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
```

Después `exec bash`, `nvm install 22`, y reinstala limpio:

```bash
cd frontend && rm -rf node_modules && npm install
```

**La página no carga en `localhost:5173`**
Mira la línea `➜  Local:` de la terminal de Vite: ahí está la dirección real. Si
el proceso ya volvió al prompt, es que murió — el error está justo encima.

**El navegador de Windows no alcanza a `localhost` aunque el proceso corra en WSL**
Se rompió el reenvío de puertos de WSL2. Cierra las terminales de Ubuntu y, en
PowerShell:

```powershell
wsl --shutdown
```

**Cambié algo en `backend/.env` y no surte efecto**
Reinicia el contenedor:

```bash
docker compose restart api
```

Las variables definidas en `compose.yaml` (`APP_URL`, `FRONTEND_URL`,
`SANCTUM_STATEFUL_DOMAINS`, `DB_CONNECTION`, `APP_LOCALE`) tienen prioridad
sobre `backend/.env` a propósito.

**Quiero empezar de cero**

```bash
docker compose down
rm -rf backend/vendor backend/.env backend/database/database.sqlite
docker compose up
```

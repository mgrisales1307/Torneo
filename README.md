# NexZone — Torneos de esports

Plataforma de torneos de **Valorant, League of Legends, Dota 2, PUBG:
Battlegrounds y Free Fire**. Cualquiera puede seguir los cuadros, las tablas y
el ranking sin cuenta. Los jugadores arman equipos, se unen con un código,
vinculan su cuenta de cada juego y se inscriben individualmente o con su equipo.
El administrador crea torneos, los inicia cuando se llenan y carga los marcadores.
La plataforma confirma las inscripciones, calcula clasificaciones, avanza los
cuadros y entrega los premios a la cartera automáticamente. Todo el dinero es
virtual: los pagos y retiros son simulados, sin tarjetas ni transferencias.

- **backend/** — API en Laravel 13 con autenticación por Sanctum (sesión en cookie).
- **frontend/** — SPA en React 19 + Vite, con React Router y TanStack Query.

- **Métricas integradas:** Metabase dentro de **Organización → Métricas**, con
  tráfico, dispositivos, rendimiento y actividad de torneos; captura opcional,
  datos agregados y actualización cada cinco minutos.
- **[Documentación técnica completa](docs/README.md):** arquitectura, API,
  instalación de Metabase, despliegue, seguridad, pruebas y mantenimiento.
- **[Contribución](CONTRIBUTING.md)** · **[Seguridad](SECURITY.md)** ·
  **[Historial](CHANGELOG.md)**.

### Activar Metabase local

```bash
node docker/metricas/preparar.mjs
docker compose --env-file .env.metricas -f compose.yaml -f compose.metricas.yaml up -d --build
# Cuando http://localhost:3001/api/health esté sano:
docker compose --env-file .env.metricas -f compose.yaml -f compose.metricas.yaml exec api php artisan metricas:sincronizar
docker compose --env-file .env.metricas -f compose.yaml -f compose.metricas.yaml exec api php artisan metricas:preparar
```

Inicia el frontend como se explica abajo. Metabase se administra en
`http://localhost:3001`, con credenciales privadas en `.env.metricas`;
el dashboard aparece dentro de NexZone para `admin` y `empleado`.
Guía de indicadores, copias, versiones y solución de errores:
**[docs/metricas.md](docs/metricas.md)**. La puesta en producción de Metabase
queda documentada; esta integración se entrega configurada localmente.

## Producción: Vercel y Supabase

Aplicación: [nexzone-torneo.vercel.app](https://nexzone-torneo.vercel.app).
Supabase: [nexzone-torneo](https://supabase.com/dashboard/project/kevyxkvfhohlhlyssdvk), región São Paulo.

Laravel y la SPA compilada se publican juntos para conservar las cookies de
Sanctum en el mismo origen. Vercel ejecuta PHP 8.5 mediante el runtime comunitario
`vercel-php@0.9.0`. PostgreSQL utiliza el esquema privado `nexzone`, con RLS y
el rol limitado `nexzone_app`, a través del pooler de sesión y TLS. Las imágenes
persisten en el bucket `medios` de Supabase Storage; las credenciales se guardan
como variables sensibles de Vercel y nunca se compilan en el frontend.

La migración del 8 de octubre de 2026 conservó **24 tablas, 329 registros y
88 archivos**, comprobados campo por campo y mediante SHA-256. La copia SQLite,
el inventario y los medios están en
`backend/storage/app/private/despliegue/20261008T235300Z/`, excluidos de Git y
del despliegue. El entorno local continúa utilizando su propia SQLite.

Para volver a publicar desde una terminal con Node y Vercel CLI autenticado:

```bash
cd frontend
npm ci
npm run build
cd ..
node docker/api/preparar-vercel.mjs
```

El último comando muestra una carpeta temporal sin bases, copias ni secretos.
Entra en esa carpeta; si no tiene la vinculación local, ejecuta `vercel link`
y selecciona el proyecto existente **nexzone-torneo**. Después:

```bash
vercel deploy --prod --skip-domain
# Comprueba la URL devuelta antes de promoverla.
vercel promote URL_DEL_DESPLIEGUE
vercel alias set URL_DEL_DESPLIEGUE nexzone-torneo.vercel.app
```

En Windows con el repositorio dentro de WSL, ejecuta el preparador con **Node
de Windows**: evita enviar enlaces de WSL directamente al CLI de Windows.
La configuración de Vercel está en `backend/vercel.json`; no despliegues el
backend sin incluir el frontend compilado.

Las variables necesarias son `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`,
`FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, las `DB_*`, las `SESSION_*`,
`CACHE_STORE`, `QUEUE_CONNECTION`, `LOG_CHANNEL`, `LOG_LEVEL`, `DISCO_MEDIOS`,
`CAPTURA_MAX_KB` y las `SUPABASE_*` del disco en `config/filesystems.php`.
La `APP_KEY` original se conservó. En producción las capturas admiten hasta
**4 MiB** para ajustarse al límite de petición de Vercel; los pagos y retiros
continúan siendo simulados.

No ejecutes `migrate:fresh`, seeders ni el exportador de SQLite contra producción
para actualizar el código. Las futuras migraciones deben aplicarse con un rol
administrativo, sobre el esquema `nexzone` y con una copia previa. El rol de
Laravel tiene únicamente permisos de lectura y escritura de datos.

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

### Si ya tenías el proyecto de antes (la versión de fútbol)

`docker compose up` aplica la migración que pasa la plataforma a esports. Las
**cuentas se conservan** —cada una recibe un apodo sacado de su nombre— y las
tablas del fútbol (plantillas, goles, calendario) se borran, porque no tienen
equivalente.

Como la base ya existía, la demostración no se carga sola. Para cargarla sin
borrar tus cuentas:

```bash
docker compose exec api php artisan db:seed --class=DemostracionSeeder
```

O, para empezar de cero con todo:

```bash
docker compose exec api php artisan migrate:fresh --seed
```

Y reinstala el frontend, porque cambió la fuente:

```bash
cd frontend && npm install
```

### Credenciales de desarrollo

| Rol | Correo | Contraseña | Puede |
| --- | --- | --- | --- |
| `admin` | `admin@juegoszone.com` | `admin123` | Todo, incluida la gestión de usuarios |
| `empleado` | `empleado@juegoszone.com` | `trabajador123` | Organizar torneos, inscripciones y disputas |
| `jugador` | `jugador@juegoszone.com` | `jugador123` | Sus equipos, cuentas, inscripciones y partidos |

`jugador@` (apodo **Parcero**) capitanea Cóndores Andinos, de Valorant, y tiene
un partido esperando que confirme el resultado. Hay dos jugadores más,
`jugador2@` (**LaMona**) y `jugador3@` (**ElPaisa**), con la misma contraseña,
y 37 jugadores de relleno (`<apodo>@demo.juegoszone.com`, también
`jugador123`).

Son **exclusivamente para desarrollo local**. `admin` y `empleado` se aseguran
en cada arranque, porque sin ellas nadie puede entrar al panel de organización.
Los jugadores solo se crean junto con la demostración.

### Datos de demostración

En una base recién creada se cargan siete torneos, cada uno en un momento
distinto, para que todas las pantallas tengan algo que mostrar:

| Juego | Torneo | Formato | Estado |
| --- | --- | --- | --- |
| Valorant | Copa Neón Valorant | Eliminación doble | En curso, con un reporte por confirmar |
| Valorant | Relámpago Valorant | Eliminación simple | Inscripciones abiertas, gratis |
| League of Legends | Grieta del Pacífico | Grupos y eliminación | Grupos terminados, eliminatorias en curso |
| Dota 2 | Ancients Cup | Eliminación simple | Finalizado, con campeón |
| Dota 2 | Suizo de Roshan | Sistema suizo | Primera ronda jugada |
| PUBG | Erangel Colombia | Battle royale | Dos de cuatro partidas |
| Free Fire | Booyah Fest | Battle royale | Inscripciones pagas y check-in abierto |

Los cuadros y resultados se generan con los mismos servicios que usa la
aplicación, así que la demostración nunca contradice las reglas. Se siembra
**solo en una base recién creada**: el arranque del contenedor ejecuta
`migrate --seed` cada vez, y si la condición fuera más laxa, un reinicio le
devolvería los datos de prueba a quien acaba de borrarlos.

### Guion de exposición del flujo automático

La base local ya contiene los escenarios de `ExposicionSeeder`. Para agregarlos
a otra base existente, sin borrar datos ni duplicar los torneos:

```bash
docker compose exec api php artisan db:seed --class=ExposicionSeeder
```

1. Entra con `jugador@juegoszone.com` / `jugador123`. En **Cartera** verás
   el premio de **Copa del ganador — Premio en cartera**: $50.000 virtuales.
2. Abre **Arena 1v1 — Último cupo**, inscríbete y elige pago simulado o cartera.
   El cuarto cupo confirmado activa la cuenta regresiva de 60 segundos.
3. Entra con `admin@juegoszone.com` / `admin123`, abre **Organización → Torneos**,
   y pulsa **Iniciar** cuando termine el contador. También tienes
   **Duelo de campeones — Listo para iniciar** con todos sus cupos completos.
4. Abre los partidos del cuadro y carga sus marcadores. Las siguientes rondas,
   el partido por el tercer puesto y el cierre se generan automáticamente.
5. Consulta la clasificación final y vuelve a **Cartera** con el ganador:
   el premio ya está acreditado. Se puede usar para otra inscripción o hacer
   un retiro simulado. Ninguna operación mueve dinero real.

También hay ejemplos en curso de doble eliminación (**Neón Masters**, con
revancha automática si gana la llave inferior), grupos (**Grieta Champions**),
suizo (**Roshan Challenge**) y battle royale por equipos (**Booyah Showcase**).
**Booyah Abierto** permite inscribir una escuadra y **Erangel Solo** permite
inscribirse individualmente en battle royale. Parcero capitanea **Cóndores Demo**,
una escuadra completa con cuentas de Free Fire vinculadas.

Los nuevos torneos se publican al crearlos. El administrador solo necesita definir
sus reglas, iniciar la competición y cargar resultados. Los premios van a quien
inscribió, aunque después cambie la capitanía. Retirar una inscripción antes del
inicio o cancelar el torneo devuelve el importe virtual. Repetir una petición no
duplica pagos ni premios. Una corrección que intenta recuperar un premio ya
gastado se rechaza para conservar los saldos y resultados consistentes.

### Portadas de los juegos

Están en `frontend/src/assets/juegos/`, una por juego y con su nombre
(`valorant.webp`, `lol.webp`, `dota2.webp`, `pubg.webp`, `freefire.webp`). Se
recortó el marco que traían las capturas y se pasaron a WebP: las cinco juntas
pesan 176 KB, frente a 1,7 MB de los originales.

Para cambiar una basta con reemplazar el archivo manteniendo el nombre (sirve
png, jpg, webp o avif). Si falta alguna, la tarjeta se pinta con el color del
juego y su sigla, así que la página nunca se rompe.

Son arte oficial de Riot, Valve, Krafton y Garena: sirven para la entrega
académica, no para publicar la plataforma comercialmente.

### El vídeo de bienvenida

La portada abre con el cinemático `NexZone_Cinematic_Final.mp4`, a todo el
ancho y con el título encima. Arranca silenciado —los navegadores no dejan
reproducir sonido sin que la persona lo pida— y el botón de la esquina le da
voz.

**Cómo se preparó**, por si hay que rehacerlo con otro vídeo (ffmpeg):

1. El original es 1280 × 720, 24 fps, 20 s y 12,6 MB.
2. Se recodificó en H.264 con CRF 28 y `+faststart`, para que empiece a
   reproducirse antes de bajar entero: **4,1 MB** (`bienvenida-720.mp4`).
3. Una versión para móviles en 854 × 480 con CRF 28: **2,3 MB**
   (`bienvenida-480.mp4`). La elige el navegador según el ancho de la pantalla.
4. El cartel es el fotograma del segundo 15 —los personajes de todos los juegos
   alrededor del trofeo— en JPEG: 99 KB (`bienvenida-cartel.jpg`).

Quien pide movimiento reducido, quien navega con ahorro de datos y quien está
en 2G recibe solo el cartel.

El título va abajo a la izquierda, sobre un velo degradado: el cinemático tiene
escenas de humo casi blanco, y sin el velo el texto se perdería en ellas.

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

Una sola familia, **Pitagon Sans** (licencia OFL), la misma del proyecto juzt.
Se sirve desde `frontend/src/assets/fuentes/`, nunca desde un CDN: así la
aplicación no depende de un tercero y no se filtra la IP de quien nos visita.

La jerarquía de títulos es la de juzt, con sus mismos tamaños fluidos
(`frontend/src/estilos/tokens.css`):

| Clase | Tamaño | Peso | Uso |
| --- | --- | --- | --- |
| `.titulo-pagina` | `clamp(3rem, 5.4vw, 6.6rem)` | 900 | El título de cada pantalla |
| `.titulo-seccion` | `clamp(2.5rem, 3.9vw, 4rem)` | 900 | Secciones grandes de las páginas públicas |
| `.titulo-bloque` | `clamp(1.55rem, 2vw, 1.9rem)` | 800 | Tarjetas, paneles y diálogos |
| `.entradilla` | `clamp(1.02rem, 1.15vw, 1.14rem)` | 400 | El párrafo que acompaña a un título |
| `.rotulo` | `0.68rem`, versales, `0.14em` | 600 | La etiqueta sobre un título o un dato |

Los títulos llevan el interletraje cerrado (−0.045em, −0.04em y −0.035em) y el
texto corrido va en 400, sin tocar.

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
  app/Enumeraciones/        Juego, formatos, fases, estados de torneo, partido e inscripción.
  app/Models/               Torneo, Fase, Partido, Equipo, CuentaDeJuego, Inscripcion, Orden…
  app/Policies/             Quién puede hacer qué: capitán, miembro u organización.
  app/Servicios/
    Equipos/                Crear equipos, unirse con código, suplentes y capitanía.
    Inscripciones/          Inscripción, revisión y check-in.
    Torneos/                Inicio, avance, resultados, tablas y ranking.
    Torneos/Cuadros/        Los algoritmos: eliminación simple y doble, grupos y suizo.
    Pagos/                  Contrato de pasarela, pasarela simulada y gestor de cobros.
    Medios/                 Subida y validación de logos y capturas.
  app/Http/Controllers/Api/V1/
    Publico/                Lo que ve cualquier visitante.
    Panel/                  El panel del jugador.
    Admin/                  El panel de la organización.
  tests/                    Pruebas del dominio, despliegue, permisos y métricas.

frontend/src/
  paginas/publico/          Portada, juego, torneos, torneo, partido, equipo y jugador.
  paginas/acceso/           Ingreso y registro.
  paginas/panel/            Pendientes, equipos, inscripciones, cuentas y pago.
  paginas/admin/            Resumen, torneos, inscripciones, disputas y usuarios.
  componentes/torneo/       Cuadro, cruces, tablas, ranking y formularios de resultado.
  componentes/juego/        Portada de cada juego.
  componentes/equipo/       Logo del equipo.
  componentes/ui/           Botones, campos, tarjetas, diálogos, insignias.
  lib/                      Cliente de la API, formato de fechas y dinero, y los juegos.
  estilos/                  Tokens, base, componentes y páginas.
```

---

## Cómo funciona

### Los juegos

| Juego | Modo | Equipo | Cuenta que se vincula |
| --- | --- | --- | --- |
| Valorant | Duelo 5v5 | 5 titulares + 2 suplentes | Riot ID (`Nombre#LAN`) |
| League of Legends | Duelo 5v5 | 5 titulares + 2 suplentes | Riot ID |
| Dota 2 | Duelo 5v5 | 5 titulares + 2 suplentes | Steam ID (17 cifras) |
| PUBG: Battlegrounds | Battle royale | Escuadra de 4 + 1 suplente | Nombre en PUBG |
| Free Fire | Battle royale | Escuadra de 4 + 1 suplente | UID de Free Fire |

Cada regla que cambia de un juego a otro vive en el enum `Juego`: el formato de
la cuenta, cuántos caben en una sala y la tabla de puntos del battle royale (la
de PUBG Global Series y la de Free Fire World Series, más un punto por baja).

### El recorrido de un equipo

```
  crear equipo  →  compartir el código  →  cada uno vincula su cuenta del juego
                                                         │
  inscribirse → pago simulado o cartera → cupo confirmado → torneo lleno
  → cuenta regresiva → administrador inicia → carga marcadores
  → avance y clasificación automáticos → premios en cartera
```

- **Capitán** no es un rol de la cuenta: es un cargo dentro de cada equipo.
  El capitán inscribe al equipo. La persona que inscribió paga y recibe el premio.
  En la modalidad individual se inscribe directamente el jugador.
- Para inscribirse, el equipo necesita la **plantilla titular completa** y que
  **todos** tengan su cuenta del juego vinculada. Nadie puede jugar el mismo
  torneo con dos equipos.
- Desde la confirmación del cupo hasta el cierre del torneo, la plantilla y las cuentas **no cambian**.
- Los torneos nuevos no requieren revisión manual ni check-in. Los torneos históricos
  conservan su flujo anterior para no alterar las competiciones ya existentes.

Una inscripción nueva pasa por `pendiente_pago` → `aprobada`,
y puede terminar en `rechazada` o `retirada`. El estado no se cambia a mano en
ningún controlador: lo mueve `GestorDeInscripciones`.

### Los formatos

La estructura de un torneo sigue el modelo de Toornament: **torneo → fase →
partido**. La organización elige el formato y, al iniciar, `InicioDeTorneo`
siembra a los equipos por el ranking del juego y arma la primera fase.

| Formato | Cómo se arma |
| --- | --- |
| Eliminación simple | Cuadro a potencia de dos con orden de siembra clásico (1 contra 16, 8 contra 9…). Los byes caen en las mejores semillas. |
| Eliminación doble | Igual, más una llave inferior y una gran final. Si gana quien viene de abajo se crea la revancha automáticamente. |
| Grupos y eliminación | Reparto en serpiente, todos contra todos en cada grupo y, al terminar, eliminatorias con los clasificados (el primero de un grupo cruza con el último clasificado de otro). |
| Sistema suizo | Cada ronda empareja récords iguales sin repetir rivales. Si son impares, descansa el peor clasificado y cuenta como victoria. |
| Battle royale | Todos en cada partida; la tabla suma puntos por puesto y por bajas. |

Los partidos que no se van a jugar (los byes) no se guardan: quien pasa directo
aparece ya en la ronda siguiente. Cada partido sabe a dónde van su ganador y su
perdedor, así que avanzar en el cuadro es seguir un puntero.

### Los resultados

En los torneos nuevos, solo la organización carga los marcadores. Una serie
terminada avanza automáticamente al ganador y recalcula las tablas; battle
royale requiere el puesto y las bajas de todos los participantes de cada partida.
Los siguientes reportes por capitanes corresponden únicamente al flujo histórico.

```
  por_jugar ──un capitán reporta──► reportado ──el rival confirma──► finalizado
                                        │
                                        └──el rival reporta otro──► en_disputa ──organización──► finalizado
```

Los capitanes reportan el marcador de la serie con la captura de la pantalla
final. Si los dos reportes coinciden, el partido se cierra solo y el ganador
avanza; si no, queda en disputa y la organización decide con las dos capturas a
la vista. Así nadie tiene que esperar a un árbitro cuando los dos equipos están
de acuerdo.

El marcador tiene que ser posible para la serie (al mejor de 3: 2-0 o 2-1). La
organización puede corregir un resultado mientras el partido siguiente no se
haya jugado. Una corrección de la final recalcula el campeón y ajusta sus premios
de forma transaccional; no permite saldos negativos.

### Tablas y ranking

Ninguna tabla se guarda en una columna: se calculan siempre a partir de los
partidos finalizados, así que es imposible que contradigan a los resultados. Se
guardan en caché y los servicios que tocan resultados la invalidan.

- **Grupos:** 3 puntos por victoria; desempatan la diferencia de mapas y los
  mapas ganados.
- **Suizo:** victorias; desempata el Buchholz (las victorias de los rivales
  enfrentados).
- **Battle royale:** puntos; desempatan las partidas ganadas y las bajas.
- **Ranking de cada juego:** Elo en los de duelo (todos arrancan en 1000) y
  puntos acumulados en battle royale. De aquí sale la siembra.

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
  tuyo o si eres el capitán. Cambiar un identificador en la URL devuelve 403,
  no los datos de otro.
- **Lo público y lo privado.** El perfil público muestra el apodo y las cuentas
  de juego; el nombre real y el correo no salen nunca. El código de invitación
  solo lo ven los miembros del equipo, y las capturas de un reporte, los dos
  equipos y la organización.
- **Nada de asignación masiva a ciegas.** Estados, montos y marcadores no son
  `fillable`: los escriben los servicios que conocen las reglas.
- **Las imágenes se validan de verdad** —tipo real del archivo, peso y
  dimensiones— y se guardan con un nombre generado por el servidor. SVG no se
  acepta: es XML y puede traer scripts dentro.
- **Límite de peticiones** en ingreso (por correo e IP), registro, pagos,
  subidas, códigos de invitación (para que no se puedan adivinar a fuerza de
  intentos) y la API en general.
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
| Jugador | `/api/v1/panel/…` | Cualquier cuenta; lo del capitán lo decide cada política |
| Organización | `/api/v1/admin/…` | `admin`, `empleado` |

Las rutas principales:

```
GET    /api/v1/inicio                              Portada: juegos, torneos activos, resultados
GET    /api/v1/juegos/{juego}                      Ficha del juego, sus torneos y su ranking
GET    /api/v1/torneos                             Torneos publicados (filtros: juego, estado, buscar)
GET    /api/v1/torneos/{slug}                      Ficha del torneo
GET    /api/v1/torneos/{slug}/cuadro               Fases, partidos y tablas
GET    /api/v1/torneos/{slug}/equipos              Equipos aprobados, con semilla y grupo
GET    /api/v1/partidos/{id}                       Partido; los reportes, solo a sus equipos y la organización
GET    /api/v1/equipos/{slug}                      Ficha del equipo
GET    /api/v1/jugadores/{apodo}                   Perfil público del jugador

POST   /api/v1/registro                            Crear cuenta (apodo, nombre, correo, clave)
POST   /api/v1/ingreso                             Entrar
POST   /api/v1/salida                              Salir

PUT    /api/v1/panel/cuentas/{juego}               Vincular la cuenta de un juego
POST   /api/v1/panel/equipos                       Crear equipo
POST   /api/v1/panel/unirse                        Entrar a un equipo con su código
PATCH  /api/v1/panel/equipos/{slug}/miembros/{id}  Titular o suplente; ceder la capitanía
POST   /api/v1/panel/torneos/{slug}/inscripciones  Inscribir un equipo
POST   /api/v1/panel/inscripciones/{id}/orden      Generar la orden de pago
POST   /api/v1/panel/inscripciones/{id}/check-in   Hacer el check-in
POST   /api/v1/panel/partidos/{id}/reporte         Reportar el marcador con su captura

POST   /api/v1/admin/torneos                       Crear torneo
POST   /api/v1/admin/torneos/{slug}/estado         Publicar, cerrar inscripciones o cancelar
POST   /api/v1/admin/torneos/{slug}/iniciar        Sembrar y armar el cuadro
POST   /api/v1/admin/inscripciones/{id}/aprobar    Aprobar una inscripción
GET    /api/v1/admin/partidos                      Disputas y reportes sin confirmar
POST   /api/v1/admin/partidos/{id}/resultado       Fijar o corregir el marcador
POST   /api/v1/admin/partidos/{id}/battle-royale   Cargar puestos y bajas de una partida
```

La lista completa: `docker compose exec api php artisan route:list --except-vendor`.

---

## Lo que falta para la próxima etapa

- **Verificación automática de cuentas** con las API de Riot, Steam, Krafton y
  Garena. Hoy se valida el formato y que nadie más la tenga.
- **Resultados automáticos.** Las mismas API permiten traer el marcador de una
  partida sin que nadie lo reporte.
- **Plazo para confirmar.** Que un reporte sin respuesta se dé por bueno pasado
  un tiempo, con una tarea programada.
- **Pasarela de pago real.** Implementar `PasarelaDePagos` con el proveedor que
  se elija y cambiar `PASARELA_PAGOS` en el entorno.
- **Notificaciones** por correo cuando abre el check-in o el rival reporta.
- **Consultar a Coljuegos** antes de cobrar inscripciones con premio en dinero
  real.

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

**Los logos y las capturas no se ven (salen como imagen rota, o dan 403)**
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

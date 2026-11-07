# Pruebas de LIGORA

Todo se ejecuta con un comando desde la raíz del proyecto, en Ubuntu/WSL:

    ./ejecutar-pruebas.sh              # backend + frontend + end to end
    ./ejecutar-pruebas.sh backend      # solo PHPUnit
    ./ejecutar-pruebas.sh frontend     # solo Vitest
    ./ejecutar-pruebas.sh e2e          # solo Playwright
    ./ejecutar-pruebas.sh e2e --ver    # con el navegador a la vista

## Dónde está cada cosa

| Capa | Carpeta | Herramienta |
|---|---|---|
| Unitarias del backend | `backend/tests/Unit/` | PHPUnit |
| Feature (API) del backend | `backend/tests/Feature/` | PHPUnit, SQLite en memoria |
| Frontend | `frontend/src/pruebas/*.prueba.{js,jsx}` | Vitest + Testing Library |
| End to end | `e2e/pruebas/*.spec.js` | Playwright (Chromium) |

## Requisitos

- PHP 8.4 y Composer (`sudo apt install composer`).
- Node 22.
- Docker **no** hace falta: los end to end levantan la API con
  `php artisan serve` y una base SQLite propia (`backend/database/e2e.sqlite`),
  que se recrea en cada ejecución y no toca tu base de desarrollo.
- La primera vez, Playwright descarga Chromium. Si el navegador no arranca por
  librerías del sistema: `cd e2e && sudo npx playwright install-deps chromium`.

## Qué cubren las pruebas nuevas

- **Unitarias:** reglas de los enums (plantillas, formato de cuentas, puntos de
  battle royale, ciclo de torneo e inscripción) y formato de dinero.
- **Feature:** alta, edición, estados y baja de torneos; listado y filtros de
  usuarios; endpoints públicos (qué se ve y qué no); códigos de invitación,
  baja de equipos, validaciones y límite de intentos; slugs.
- **Frontend:** `formato.js`, `juegos.js`, errores de la API, `RutaProtegida`,
  `TarjetaDeTorneo`, pantallas de Ingreso y Registro.
- **End to end:** registro e ingreso por pantalla, permisos por rol, 401/403 en
  la API, y el ciclo completo de un torneo individual (inscripción, pago
  simulado, cupo lleno, inicio, resultado y campeón).

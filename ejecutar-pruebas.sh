#!/usr/bin/env bash
# Ejecuta las pruebas de LIGORA en Ubuntu con un solo comando.
#
#   ./ejecutar-pruebas.sh               todo: backend, frontend y end to end
#   ./ejecutar-pruebas.sh backend       solo PHPUnit (unitarias + feature)
#   ./ejecutar-pruebas.sh frontend      solo Vitest
#   ./ejecutar-pruebas.sh e2e           solo Playwright (navegador real)
#   ./ejecutar-pruebas.sh backend frontend   cualquier combinación
#
# Opciones:
#   --rapido    no instala dependencias si ya existen (por defecto ya lo hace)
#   --ver       en e2e, abre el navegador a la vista
#
# Sale con código 0 solo si TODO lo ejecutado pasó, para usarlo igual en CI.
set -uo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUITES=()
VER=0

for argumento in "$@"; do
  case "$argumento" in
    backend|frontend|e2e) SUITES+=("$argumento") ;;
    --ver) VER=1 ;;
    --rapido) ;;
    -h|--help) sed -n '2,15p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *) echo "Argumento desconocido: $argumento (usa --help)" >&2; exit 2 ;;
  esac
done

[ ${#SUITES[@]} -eq 0 ] && SUITES=(backend frontend e2e)

RESULTADOS=()
FALLOS=0

titulo() { printf '\n\033[1;36m== %s ==\033[0m\n' "$1"; }
necesita() { command -v "$1" >/dev/null 2>&1 || { echo "Falta '$1'. $2" >&2; return 1; }; }
registrar() {
  if [ "$2" -eq 0 ]; then RESULTADOS+=("  ✔ $1"); else RESULTADOS+=("  ✘ $1"); FALLOS=$((FALLOS + 1)); fi
}

correr_backend() {
  titulo "Backend (PHPUnit)"
  necesita php "Instala PHP 8.4: https://laravel.com/docs/installation" || return 1
  necesita composer "Instálalo con: sudo apt install composer" || return 1
  cd "$RAIZ/backend" || return 1

  [ -d vendor ] || composer install --no-interaction --prefer-dist || return 1

  # Las pruebas necesitan una clave de cifrado de la aplicación.
  if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --ansi
  elif ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --ansi
  fi

  vendor/bin/phpunit --colors=always --testdox
}

correr_frontend() {
  titulo "Frontend (Vitest)"
  necesita node "Instala Node 22 con nvm: https://github.com/nvm-sh/nvm" || return 1
  cd "$RAIZ/frontend" || return 1

  [ -d node_modules ] || npm install || return 1

  npm test
}

correr_e2e() {
  titulo "End to end (Playwright + navegador real)"
  necesita php "Los end to end levantan la API con php artisan serve." || return 1
  necesita node "Instala Node 22 con nvm." || return 1

  [ -d "$RAIZ/backend/vendor" ] || (cd "$RAIZ/backend" && composer install --no-interaction --prefer-dist) || return 1
  [ -d "$RAIZ/frontend/node_modules" ] || (cd "$RAIZ/frontend" && npm install) || return 1

  cd "$RAIZ/e2e" || return 1
  [ -d node_modules ] || npm install || return 1

  # El navegador se descarga una vez. Si faltan librerías del sistema, se
  # instalan con: sudo npx playwright install-deps chromium
  npx playwright install chromium || return 1

  if [ "$VER" -eq 1 ]; then
    npx playwright test --headed
  else
    npx playwright test
  fi
}

for suite in "${SUITES[@]}"; do
  ( "correr_$suite" )
  registrar "$suite" $?
done

titulo "Resumen"
printf '%s\n' "${RESULTADOS[@]}"

if [ "$FALLOS" -ne 0 ]; then
  printf '\n\033[1;31m%d suite(s) con fallos.\033[0m\n' "$FALLOS"
  exit 1
fi

printf '\n\033[1;32mTodo en verde.\033[0m\n'

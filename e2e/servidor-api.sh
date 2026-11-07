#!/usr/bin/env bash
# Levanta la API de Laravel para las pruebas de extremo a extremo, sin Docker.
#
# Usa una base SQLite propia (backend/database/e2e.sqlite) que se crea de cero
# en cada ejecución, así que NO toca tu base de desarrollo. Las variables de
# entorno tienen prioridad sobre backend/.env.
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Puertos propios, distintos de los de desarrollo (8000 y 5173), para poder
# correr las pruebas con tu entorno normal encendido.
PUERTO_API="${E2E_API_PORT:-8010}"
PUERTO_WEB="${E2E_WEB_PORT:-5183}"
cd "$RAIZ/backend"

if [ ! -d vendor ]; then
  echo "Falta backend/vendor. Ejecuta primero: (cd backend && composer install)" >&2
  exit 1
fi

BASE="$RAIZ/backend/database/e2e.sqlite"
rm -f "$BASE"
touch "$BASE"

export APP_ENV=local
export APP_DEBUG=false
export APP_URL="http://127.0.0.1:${PUERTO_API}"
export FRONTEND_URL="http://127.0.0.1:${PUERTO_WEB}"
export SANCTUM_STATEFUL_DOMAINS="localhost:${PUERTO_WEB},127.0.0.1:${PUERTO_WEB}"
export DB_CONNECTION=sqlite
export DB_DATABASE="$BASE"
export SESSION_DRIVER=file
# En memoria: cada petición empieza con los contadores en cero, así los
# límites de intentos no vuelven inestables las pruebas.
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array

export LOG_LEVEL=error
export BCRYPT_ROUNDS=4
export APP_LOCALE=es
export APP_FAKER_LOCALE=es_ES
export PASARELA_PAGOS=simulada
export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"

php artisan migrate:fresh --seed --force

export PHP_CLI_SERVER_WORKERS=4
exec php artisan serve --host=127.0.0.1 --port="$PUERTO_API" --no-reload

#!/usr/bin/env bash
#
# Juegos Zone — Torneos
# Prepara el proyecto completo después de un `git clone`.
#
#   ./setup.sh
#
# No necesitas tener PHP ni Composer instalados: si no están disponibles,
# el script los ejecuta dentro de un contenedor temporal.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

bold() { printf '\n\033[1m%s\033[0m\n' "$1"; }
info() { printf '  %s\n' "$1"; }
fail() { printf '\n\033[31mError:\033[0m %s\n\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------- requisitos
bold "1/6  Comprobando requisitos"

command -v docker >/dev/null 2>&1 || fail "Docker no está instalado. Instala Docker Desktop y vuelve a ejecutar este script."
docker compose version >/dev/null 2>&1 || fail "Necesitas Docker Compose v2 (viene con Docker Desktop actualizado)."
docker info >/dev/null 2>&1 || fail "Docker está instalado pero no se está ejecutando. Abre Docker Desktop y espera a que arranque."
command -v node >/dev/null 2>&1 || fail "Node.js no está instalado. Instala Node.js 20.19 o superior."

NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"
if [ "$NODE_MAJOR" -lt 20 ]; then
  fail "Tienes Node $(node -v) y el frontend necesita Node 20.19 o superior."
fi

info "Docker en ejecución"
info "Node $(node -v)"

# Ejecuta composer/php con lo que haya en la máquina; si no hay, usa Docker.
HAS_LOCAL_PHP=0
if command -v php >/dev/null 2>&1 && command -v composer >/dev/null 2>&1; then
  HAS_LOCAL_PHP=1
  info "PHP y Composer locales detectados"
else
  info "Sin PHP/Composer locales: se usará el contenedor oficial de Composer"
fi

run_composer() {
  if [ "$HAS_LOCAL_PHP" -eq 1 ]; then
    (cd "$BACKEND" && composer "$@")
  else
    docker run --rm --interactive=false \
      --volume "$BACKEND":/app \
      --workdir /app \
      composer:2 composer "$@"
  fi
}

run_artisan() {
  if [ "$HAS_LOCAL_PHP" -eq 1 ]; then
    (cd "$BACKEND" && php artisan "$@")
  else
    docker run --rm --interactive=false \
      --volume "$BACKEND":/app \
      --workdir /app \
      composer:2 php artisan "$@"
  fi
}

# ------------------------------------------------------------------- backend
bold "2/6  Configurando el entorno del backend"

if [ ! -f "$BACKEND/.env" ]; then
  cp "$BACKEND/.env.example" "$BACKEND/.env"
  info "Creado backend/.env a partir de .env.example"
else
  info "backend/.env ya existe (se conserva)"
fi

# El contenedor monta el proyecto como volumen: alinear el usuario evita
# problemas de permisos en Linux y WSL.
if [ "$(uname -s)" != "Darwin" ] && command -v id >/dev/null 2>&1; then
  CURRENT_UID="$(id -u)"
  CURRENT_GID="$(id -g)"
  if grep -q '^WWWUSER=' "$BACKEND/.env"; then
    sed -i.bak "s/^WWWUSER=.*/WWWUSER=$CURRENT_UID/" "$BACKEND/.env"
    sed -i.bak "s/^WWWGROUP=.*/WWWGROUP=$CURRENT_GID/" "$BACKEND/.env"
    rm -f "$BACKEND/.env.bak"
    info "WWWUSER/WWWGROUP ajustados a $CURRENT_UID:$CURRENT_GID"
  fi
fi

bold "3/6  Instalando dependencias de PHP"
if [ -d "$BACKEND/vendor/laravel/sail" ]; then
  info "vendor/ ya está instalado (se omite)"
else
  info "Esto puede tardar un par de minutos la primera vez..."
  run_composer install --no-interaction --prefer-dist
fi

# Sin APP_KEY, Laravel no puede cifrar las cookies de sesión.
if grep -q '^APP_KEY=$' "$BACKEND/.env"; then
  run_artisan key:generate --force
  info "APP_KEY generada"
else
  info "APP_KEY ya definida"
fi

# -------------------------------------------------------------------- docker
bold "4/6  Levantando el contenedor"
info "La primera vez Docker construye la imagen: puede tardar varios minutos."
(cd "$BACKEND" && docker compose up -d --build)

APP_PORT="$(grep -E '^APP_PORT=' "$BACKEND/.env" | cut -d= -f2 || true)"
APP_PORT="${APP_PORT:-80}"
if [ "$APP_PORT" = "80" ]; then
  API_URL="http://localhost"
else
  API_URL="http://localhost:$APP_PORT"
fi

info "Esperando a que la API responda en $API_URL ..."
for attempt in $(seq 1 60); do
  if curl -fsS -o /dev/null "$API_URL/up" 2>/dev/null; then
    info "API disponible"
    break
  fi
  if [ "$attempt" -eq 60 ]; then
    printf '\n'
    (cd "$BACKEND" && docker compose logs --tail 40 laravel.test) || true
    fail "La API no respondió tras 2 minutos. Revisa los registros de arriba."
  fi
  sleep 2
done

# ------------------------------------------------------------------ frontend
bold "5/6  Configurando el frontend"

if [ ! -f "$FRONTEND/.env.local" ]; then
  printf 'VITE_API_URL=%s\n' "$API_URL" > "$FRONTEND/.env.local"
  info "Creado frontend/.env.local apuntando a $API_URL"
else
  info "frontend/.env.local ya existe (se conserva)"
fi

bold "6/6  Instalando dependencias de Node"
(cd "$FRONTEND" && npm install --no-fund --no-audit)

# --------------------------------------------------------------------- listo
cat <<EOF

──────────────────────────────────────────────────────────────
  Todo listo.

  API      $API_URL   (ya está corriendo en Docker)
  Frontend arranca con:

      cd frontend && npm run dev

  Luego abre http://localhost:5173

  Usuarios de desarrollo
    admin@juegoszone.com  /  CambiaEstaClave123   (admin)
    test@example.com      /  password             (usuario)
──────────────────────────────────────────────────────────────

EOF

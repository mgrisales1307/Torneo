#!/bin/bash
# Prepara la API y la deja lista para servir. Es idempotente: se puede
# ejecutar tantas veces como se reinicie el contenedor.
set -e

cd /app

# El proyecto entra al contenedor como volumen. Adoptamos el usuario dueño de
# esos archivos para no dejar nada como root en la carpeta del host, sin
# importar el UID/GID del equipo que clonó el repositorio.
HOST_UID="$(stat -c %u /app)"
HOST_GID="$(stat -c %g /app)"

if [ "$HOST_UID" != "0" ]; then
    groupadd -o -g "$HOST_GID" app 2>/dev/null || true
    useradd -o -u "$HOST_UID" -g "$HOST_GID" -M -d /app -s /bin/bash app 2>/dev/null || true
    RUN_AS="$HOST_UID:$HOST_GID"
else
    RUN_AS="root:root"
fi

mkdir -p /tmp/composer
chmod 777 /tmp/composer

as_host() { gosu "$RUN_AS" "$@"; }

if [ ! -f .env ]; then
    as_host cp .env.example .env
    echo "→ backend/.env creado a partir de .env.example"
fi

if [ ! -f vendor/autoload.php ]; then
    echo "→ Instalando dependencias de PHP (solo la primera vez, tarda unos minutos)..."
    as_host composer install --no-interaction --prefer-dist
fi

# Sin APP_KEY, Laravel no puede cifrar las cookies de sesión y el login falla.
if grep -q '^APP_KEY=$' .env; then
    as_host php artisan key:generate --force
fi

as_host mkdir -p \
    database \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

as_host touch database/database.sqlite

# Enlace público del disco de imágenes (escudos, uniformes y fotos). Es
# idempotente: si ya existe, no hace nada.
as_host php artisan storage:link --quiet || true

as_host php artisan migrate --seed --force

echo "→ API lista"

exec gosu "$RUN_AS" "$@"

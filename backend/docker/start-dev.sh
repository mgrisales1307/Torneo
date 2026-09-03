#!/usr/bin/env bash

set -e

mkdir -p storage/logs bootstrap/cache database
touch database/database.sqlite
chmod -R a+rwX storage bootstrap/cache
chmod a+rwx database
chmod a+rw database/database.sqlite

gosu sail php artisan migrate --seed --force

# Los archivos de ejecución pueden venir de otro equipo con un UID/GID distinto.
chmod -R a+rwX storage bootstrap/cache
chmod a+rwx database
chmod a+rw database/database.sqlite

export SUPERVISOR_PHP_USER=sail
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

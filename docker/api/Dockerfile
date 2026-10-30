# API de Laravel para desarrollo.
#
# La imagen trae PHP y Composer, así que quien clone el repositorio no
# necesita instalarlos: basta con Docker.
ARG IMAGEN_PHP=php:8.4-cli
FROM ${IMAGEN_PHP}

RUN if ! php -r 'exit(extension_loaded("pdo_pgsql") && extension_loaded("pdo_sqlite") && extension_loaded("zip") ? 0 : 1);'; then \
    apt-get update && apt-get install -y --no-install-recommends \
        git \
        gosu \
        libsqlite3-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*; \
    fi

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Cuatro procesos para que el servidor de desarrollo no se bloquee cuando el
# navegador dispara varias peticiones a la vez.
ENV PHP_CLI_SERVER_WORKERS=4 \
    COMPOSER_HOME=/tmp/composer

COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app
EXPOSE 8000
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]

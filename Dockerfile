# syntax=docker/dockerfile:1
#
# SINCHI WAYRA - imagen UNICA que sirve la SPA (Vue) y la API (Laravel) en el
# MISMO origen. Es un requisito del sistema: la autenticacion es por cookie de
# sesion (Sanctum modo SPA) y el cliente axios usa la ruta relativa /api/v1, asi
# que la SPA y la API no pueden vivir en dominios distintos (ver
# puesta_produccion.txt, seccion 3.2).
#
# El contexto de build debe ser la RAIZ del repositorio (sinchy/), porque se
# copian tanto sinchy-web/ como sinchy-api/.
#
#   Etapa 1 (web)   : Node 24 compila la SPA  ->  /web/dist
#   Etapa 2 (final) : PHP 8.4 + Nginx sirve la SPA y enruta /api y /sanctum a
#                     PHP-FPM, todo en el mismo puerto (el que inyecta $PORT).

# ---------------------------------------------------------------------------
# Etapa 1: construir la SPA (vue-tsc -b && vite build)
# ---------------------------------------------------------------------------
FROM node:24-alpine AS web

WORKDIR /web
COPY sinchy-web/package.json sinchy-web/package-lock.json ./
RUN npm ci
COPY sinchy-web/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 2: PHP 8.4 + PostgreSQL + Nginx
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

# nginx          -> servidor web (sirve la SPA y pasa /api y /sanctum a PHP)
# gettext        -> envsubst (inyecta el puerto $PORT en la plantilla de nginx)
# postgresql-dev -> cabeceras para compilar pdo_pgsql / pgsql
# libzip/libpng/libjpeg/freetype/icu/oniguruma -> zip, gd, intl, mbstring
RUN apk add --no-cache \
        nginx \
        gettext \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath gd zip intl mbstring opcache \
    && rm -rf /var/cache/apk/*

# PHP-FPM: escuchar solo en loopback y HEREDAR el entorno del contenedor.
# clear_env=no es imprescindible en un PaaS/Docker: las variables (APP_KEY, DB_*,
# SANCTUM_*, ...) llegan como variables de entorno del contenedor, no como
# archivo .env, y por defecto PHP-FPM las borraria.
RUN sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api
COPY sinchy-api/composer.json sinchy-api/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --no-scripts
COPY sinchy-api/ ./
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# La SPA compilada queda como raiz de Nginx.
COPY --from=web /web/dist /var/www/web

COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh \
    && sed -i 's/\r$//' /usr/local/bin/start.sh \
    && rm -f /etc/nginx/http.d/default.conf

# El proveedor inyecta PORT. Sin la variable se usa 8080.
ENV PORT=8080
EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]

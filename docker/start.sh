#!/bin/sh
# SINCHI WAYRA - arranque del contenedor.
#
# Deja todo listo y levanta PHP-FPM + Nginx en el puerto que inyecta el
# proveedor ($PORT). Se ejecuta en CADA arranque del contenedor: cachea la
# configuracion con las variables ya inyectadas, aplica migraciones y arranca.
set -e

cd /var/www/api

echo "[start] Generando la configuracion de Nginx para el puerto ${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

echo "[start] Cacheando configuracion, rutas y vistas"
# config:cache DEBE correr aqui (y no en el build): lee las variables de
# entorno que el proveedor inyecta en tiempo de ejecucion. Es critico: si
# falla, se detiene el arranque.
php artisan config:cache
# Rutas y vistas: si no se pueden cachear (por ejemplo una ruta con closure),
# se avisa y se sigue con el arranque normal en vez de bloquear el servicio.
php artisan route:cache || echo "[start] AVISO: no se pudo cachear rutas; se usaran dinamicas."
php artisan view:cache || echo "[start] AVISO: no se pudieron cachear vistas."

echo "[start] Aplicando migraciones (con reintentos por si la base aun arranca)"
intentos=0
until php artisan migrate --force; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 30 ]; then
        echo "[start] ERROR: la base de datos no respondio tras $intentos intentos." >&2
        exit 1
    fi
    echo "[start] Esperando a la base de datos... (intento $intentos)"
    sleep 3
done

# Seeders de datos base: se corren UNA SOLA VEZ. Definir SEED=1 en las variables
# del servicio, desplegar, y luego QUITAR la variable para no repetirlos en cada
# reinicio. Ver el Anexo F de puesta_produccion.txt.
if [ "${SEED:-0}" = "1" ]; then
    echo "[start] Sembrando datos base (SEED=1)"
    php artisan db:seed --class=RolPermisosSeeder       --force
    php artisan db:seed --class=CatalogoGeograficoSeeder --force
    php artisan db:seed --class=PresentacionesSeeder    --force
    php artisan db:seed --class=ParametrosSeeder        --force
    php artisan db:seed --class=CompradoresSeeder       --force
    php artisan db:seed --class=AdminSeeder             --force
fi

# Lo que Laravel escribe en disco debe ser del usuario de PHP-FPM (www-data).
chown -R www-data:www-data storage bootstrap/cache || true

echo "[start] Arrancando PHP-FPM y Nginx"
php-fpm -D
exec nginx -g 'daemon off;'

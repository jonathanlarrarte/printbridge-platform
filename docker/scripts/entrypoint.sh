#!/bin/sh
set -e

# Compartido por los 3 contenedores que corren esta imagen (app/queue/
# scheduler, ver docker-compose.yml) -- RUN_MIGRATIONS solo se setea en
# `app` para que las migraciones corran una sola vez al levantar el stack,
# no tres veces en paralelo.
if [ "$RUN_MIGRATIONS" = "true" ]; then
  echo "[entrypoint] Esperando a que Postgres acepte conexiones..."
  until php artisan db:show > /dev/null 2>&1; do
    sleep 2
  done

  echo "[entrypoint] Corriendo migraciones..."
  php artisan migrate --force

  echo "[entrypoint] Cacheando config/rutas/vistas para produccion..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"

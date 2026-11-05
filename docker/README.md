# Deploy en un servidor de cliente (aislado, sin tocar su Postgres/PHP)

Pensado para instalar este stack en un servidor que YA corre otras cosas
(Nginx y Postgres del cliente, posiblemente otras apps) sin tocar nada de
lo que ya existe ahí. Todo lo de PrintBridge vive en contenedores propios;
lo único que se agrega al sistema del host es **un archivo de vhost nuevo**
en Nginx.

## Prerrequisitos

**Docker.** Si el servidor todavía no lo tiene:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # para no tener que usar sudo en cada comando (requiere volver a loguearte)
```

**El código.** El repo es privado, así que `git clone` normal no va a andar
sin un token. Dos formas, elegí una:

```bash
# Opcion A: clonar con un Personal Access Token (pedí uno de solo lectura,
# "Contents: Read-only" en los permisos del repo, al dueño del repo)
git clone https://TU_USUARIO:TU_TOKEN@github.com/jonathanlarrarte/printbridge-platform.git
cd printbridge-platform

# Opcion B: si no queres manejar un token en ese servidor, empaquetar
# localmente y subir por scp (igual que con el agente):
#   tar --exclude=.git --exclude=node_modules --exclude=vendor -czf app.tar.gz .
#   scp app.tar.gz usuario@servidor-cliente:/ruta/
#   (en el servidor) tar -xzf app.tar.gz -C printbridge-platform/
```

## 0. Inventario antes de tocar nada (de solo lectura)

Corré esto primero y confirmá que no hay conflictos antes de seguir:

```bash
docker --version && docker compose version   # Docker instalado?
sudo ss -tlnp | grep -E ':(8000|5432|6379)\b' # algo ya usando estos puertos?
docker ps -a                                   # hay otros stacks de Docker corriendo?
sudo nginx -t                                  # su config actual esta sana?
df -h /                                        # espacio en disco (imagenes + volumenes)
```

Si el puerto 8000 ya está en uso por otra cosa del cliente, cambiá
`APP_PORT` en `docker/compose.env` a otro puerto libre (ej. `8090`) antes
de seguir.

## 1. Preparar los `.env`

```bash
cp docker/compose.env.example docker/compose.env
cp docker/app.env.example docker/app.env
```

Completá `docker/compose.env` con una contraseña nueva para Postgres y
otra para Redis (no reutilices las de ningún otro ambiente). Copiá esas
mismas dos contraseñas a `docker/app.env` (`DB_PASSWORD` y
`REDIS_PASSWORD` tienen que coincidir entre los dos archivos). Completá
también `APP_URL` en `docker/app.env` con el dominio real de este deploy.

## 2. Generar una `APP_KEY` propia

Nunca reuses la de otro ambiente -- cifra sesiones y datos sensibles, tiene
que ser única por instalación.

```bash
docker run --rm -v "$(pwd):/app" -w /app composer:2 \
  php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Pegá el resultado en `APP_KEY=` dentro de `docker/app.env`.

## 3. Levantar el stack

```bash
docker compose --env-file docker/compose.env up -d --build
```

La primera vez construye las 3 imágenes (asset build con Vite +
dependencias de Composer) y arranca Postgres/Redis propios, corre las
migraciones automáticamente (`RUN_MIGRATIONS=true` solo en `app`, ver
`docker/scripts/entrypoint.sh`), y deja los 5 contenedores corriendo:
`postgres`, `redis`, `app`, `queue`, `scheduler`.

## 4. Verificar que levantó bien

```bash
docker compose ps                        # los 5 "healthy"/"running"
docker compose logs app --tail=50        # sin errores de conexion a DB/Redis
curl -s http://127.0.0.1:8000/health     # {"ok":true}
curl -s http://127.0.0.1:8000/v1         # json de auto-descubrimiento
```

Si `app` no levanta, revisá `docker compose logs app` -- el error casi
siempre es `DB_PASSWORD`/`REDIS_PASSWORD` no coincidiendo entre
`docker/compose.env` y `docker/app.env`.

## 5. El único cambio en el Nginx del cliente: un vhost nuevo

**No edites ningún archivo de Nginx que ya exista.** Creá uno nuevo.

### SSL con certificado de origen de Cloudflare (este es el caso: ya tienen el subdominio detrás de Cloudflare)

Mismo esquema que ya corre en el servidor actual (`impryxa.vekronis.com`):
Cloudflare maneja el cert público de cara al visitante; el servidor de
origen usa un **Origin Certificate** propio de Cloudflare (no es Let's
Encrypt) que solo es válido para conexiones que pasan por Cloudflare.

1. En el dashboard de Cloudflare del subdominio: **SSL/TLS → Origin
   Server → Create Certificate**. Dejá los defaults (RSA, 15 años),
   confirmá que el hostname listado sea el subdominio correcto, y generá.
   Te da dos bloques de texto: el certificado y la clave privada.
2. En **SSL/TLS → Overview**, el modo de encriptación tiene que estar en
   **Full (strict)** -- así Cloudflare exige que el origen presente este
   certificado, no uno autofirmado cualquiera.
3. En el servidor, guardalos con los mismos permisos que ya usa el
   servidor actual (carpeta sin acceso de otros usuarios, clave privada
   solo lectura para root):

```bash
sudo mkdir -p /etc/nginx/ssl
sudo chmod 700 /etc/nginx/ssl
sudo nano /etc/nginx/ssl/TU-SUBDOMINIO.crt   # pegar el certificado
sudo nano /etc/nginx/ssl/TU-SUBDOMINIO.key   # pegar la clave privada
sudo chmod 644 /etc/nginx/ssl/TU-SUBDOMINIO.crt
sudo chmod 600 /etc/nginx/ssl/TU-SUBDOMINIO.key
```

4. Esas dos rutas van en `ssl_certificate` / `ssl_certificate_key` del
   vhost de abajo.

> Si en cambio **no** usan Cloudflare para este subdominio (DNS en gris,
> sin proxy) y necesitan un cert público real, la alternativa es Let's
> Encrypt vía `certbot --nginx -d TU-DOMINIO` (gratis, se renueva solo) --
> no aplica a este caso pero queda documentado por si cambia.

```nginx
# /etc/nginx/sites-available/TU-DOMINIO.conf
server {
    listen 80;
    server_name TU-DOMINIO;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name TU-DOMINIO;

    ssl_certificate     /etc/nginx/ssl/TU-SUBDOMINIO.crt;
    ssl_certificate_key /etc/nginx/ssl/TU-SUBDOMINIO.key;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8000;  # o el APP_PORT que hayas elegido
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/TU-DOMINIO.conf /etc/nginx/sites-enabled/
sudo nginx -t          # valida TODA la config, incluida la del cliente -- tiene que decir "ok"
sudo systemctl reload nginx   # reload, no restart -- no corta las otras conexiones activas del cliente
```

`nginx -t` antes del reload es el paso que te protege de romper algo del
cliente por un typo en tu vhost nuevo: si falla, el `reload` ni se
intenta y Nginx se queda sirviendo exactamente como estaba.

Para `/developers/` (docs) y `/downloads/` (instaladores del agente), si
los querés replicar en este servidor son simplemente dos `location`
adicionales con `alias` a carpetas del host (ver el vhost de referencia en
el servidor actual) -- no son parte del stack de Docker.

## 6. Actualizar el agente para apuntar al nuevo dominio

Los agentes ya instalados siguen reportando al dominio viejo hasta que
alguien cambie `plataforma_url` en la ventana de cada uno. Si es una
migración completa (no en paralelo), coordinar ese cambio es un paso
aparte, no automático.

## Rollback

Si algo sale mal, `docker compose down` baja los 5 contenedores sin tocar
nada del cliente (los datos quedan en los volúmenes con nombre
`printbridge-platform_postgres_data` / `..._redis_data`, no se pierden).
Borrar el vhost nuevo de `sites-enabled` y hacer `nginx -t && systemctl
reload nginx` deja al cliente exactamente como estaba antes de empezar.

## Comandos útiles día a día

```bash
docker compose logs -f app              # logs en vivo de la app
docker compose logs -f queue            # logs del worker de colas
docker compose exec app php artisan tinker
docker compose exec postgres psql -U printbridge -d printbridge_platform
docker compose restart app queue        # tras un deploy de codigo nuevo (rebuild antes)
docker compose --env-file docker/compose.env up -d --build   # deploy de una version nueva
```

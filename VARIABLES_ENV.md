# 📋 Variables de Entorno Requeridas

Este documento lista todas las variables de entorno necesarias para la aplicación. En **Coolify**, debes configurar estas variables manualmente en la sección **Environment Variables**.

## 🔧 Cómo Configurar en Coolify

1. Ve a tu aplicación en Coolify
2. Haz clic en **Environment Variables**
3. Agrega cada variable una por una, o importa desde un archivo `.env`
4. **Importante**: Las variables definidas en Coolify tienen prioridad sobre cualquier archivo `.env`

## 📝 Lista Completa de Variables

### Aplicación Base

```env
APP_NAME="API Facturación SUNAT"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://tu-dominio.com
```

### Localización

```env
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_PE
```

### Mantenimiento

```env
APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database
```

### PHP

```env
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12
```

### Logging

```env
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
```

### Base de Datos PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=facturacion_sunat
DB_USERNAME=postgres
DB_PASSWORD=tu_password_seguro
```

### Sesiones

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
```

### Queue y Broadcasting

```env
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
```

### Cache

```env
CACHE_STORE=database
# CACHE_PREFIX=
```

### Memcached (Opcional)

```env
MEMCACHED_HOST=127.0.0.1
```

### Redis (Opcional)

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Mail

```env
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### AWS S3 (Opcional)

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Laravel Sanctum

```env
SANCTUM_STATEFUL_DOMAINS=tu-dominio.com,www.tu-dominio.com
SANCTUM_EXPIRATION=1440
SANCTUM_TOKEN_PREFIX=sunat_
SANCTUM_VERIFY_IP=false
SANCTUM_LOG_USAGE=true
SANCTUM_MAX_INACTIVITY=120
SANCTUM_MAX_TOKENS=10
SANCTUM_ROTATE_TOKENS=false
SANCTUM_PURGE_DAYS=7
```

### SUNAT Configuration

```env
SUNAT_ENVIRONMENT=beta
SUNAT_CERTIFICATE_PATH=
SUNAT_CERTIFICATE_PASSWORD=
SUNAT_PRIVATE_KEY_PATH=
```

### Files Configuration

```env
FILES_STORAGE_PATH=storage/app/sunat
FILES_MAX_SIZE=10240
```

### Vite

```env
VITE_APP_NAME="${APP_NAME}"
```

### Docker (Solo para Docker Compose local)

```env
APP_PORT=80
RUN_MIGRATIONS=false
```

## ⚠️ Variables Críticas que DEBES Configurar

Estas variables son **obligatorias** y deben configurarse antes del primer despliegue:

1. **APP_KEY**: Genera con `php artisan key:generate`
2. **APP_URL**: Tu dominio completo (ej: `https://api.tu-dominio.com`)
3. **DB_PASSWORD**: Contraseña segura para PostgreSQL
4. **SANCTUM_STATEFUL_DOMAINS**: Tu dominio real
5. **SUNAT_CERTIFICATE_PATH**: Ruta al certificado SUNAT
6. **SUNAT_CERTIFICATE_PASSWORD**: Contraseña del certificado

## 🔄 Importar Variables en Coolify

### Opción 1: Manual
Copia y pega cada variable en la interfaz de Coolify.

### Opción 2: Desde archivo
1. Crea un archivo `.env` con todas las variables
2. En Coolify, ve a **Environment Variables**
3. Usa la opción de importar desde archivo (si está disponible)

### Opción 3: Usar el archivo de ejemplo
Puedes usar el archivo `env.docker.example` como referencia, pero recuerda:
- Cambiar `DB_CONNECTION` de `mysql` a `pgsql`
- Cambiar `DB_HOST` de `127.0.0.1` a `postgres`
- Actualizar `APP_URL` con tu dominio real
- Actualizar `SANCTUM_STATEFUL_DOMAINS` con tu dominio

## 📌 Notas Importantes

1. **En Coolify, las variables de entorno se configuran desde la interfaz web**, no desde archivos `.env` en el repositorio
2. Las variables definidas en Coolify tienen **prioridad** sobre cualquier archivo `.env`
3. Si cambias variables después del despliegue, necesitas **reiniciar** los contenedores
4. Algunas variables como `APP_KEY` solo se generan una vez y deben mantenerse entre despliegues


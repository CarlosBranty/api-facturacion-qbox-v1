# 🚀 Guía de Despliegue en Coolify

Esta guía te ayudará a desplegar la API de Facturación Electrónica SUNAT en Coolify usando Docker Compose con PostgreSQL.

## 📋 Requisitos Previos

- Cuenta en Coolify
- Repositorio Git configurado
- Certificados digitales SUNAT (.pfx o .pem)

## 🔧 Configuración en Coolify

### 1. Crear Nueva Aplicación

1. En Coolify, ve a **Applications** → **New Application**
2. Selecciona **Docker Compose**
3. Conecta tu repositorio Git

### 2. Configurar Dominios en Coolify

Cuando Coolify te pida configurar dominios para los servicios, sigue estas instrucciones:

#### ⚠️ IMPORTANTE: Solo nginx necesita dominio público

- **Servicio `nginx`**: ✅ **SÍ necesita dominio**
  - Dominio: `api.tu-dominio.com` (o el que prefieras)
  - Puerto: `80` (o deja vacío si Coolify lo detecta automáticamente)
  - URL completa: `https://api.tu-dominio.com` (Coolify agregará HTTPS automáticamente)
  - Si tienes un puerto específico: `https://api.tu-dominio.com:8080` (solo si es necesario)

- **Servicio `app` (PHP-FPM)**: ❌ **NO necesita dominio**
  - Este servicio es interno y solo se comunica con nginx
  - Si Coolify te pregunta, puedes dejarlo vacío o poner `localhost`
  - No es accesible desde fuera del contenedor

- **Servicio `postgres`**: ❌ **NO necesita dominio**
  - Base de datos interna, no accesible públicamente
  - Si Coolify pregunta, déjalo vacío

#### Configuración en Coolify:

1. Cuando te pregunte por el dominio del servicio **nginx**:
   - Ingresa: `api.tu-dominio.com` (sin http:// ni https://)
   - Puerto: `80` (o déjalo vacío)
   - Coolify configurará automáticamente HTTPS con Let's Encrypt

2. Si te pregunta por el dominio del servicio **app**:
   - Puedes poner: `localhost` o dejarlo vacío
   - O simplemente ignora esta pregunta si es opcional

3. **Actualiza la variable `APP_URL`** en las variables de entorno con tu dominio real:
   ```env
   APP_URL=https://api.tu-dominio.com
   ```

4. **Actualiza `SANCTUM_STATEFUL_DOMAINS`** con tu dominio:
   ```env
   SANCTUM_STATEFUL_DOMAINS=api.tu-dominio.com,www.api.tu-dominio.com
   ```

### 3. Configurar Variables de Entorno

En la sección de **Environment Variables** de Coolify, configura las siguientes variables. Puedes usar el archivo `env.docker.example` como referencia:

```env
# Aplicación
APP_NAME="API Facturación SUNAT"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://tu-dominio.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_PE

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Base de Datos PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=facturacion_sunat
DB_USERNAME=postgres
DB_PASSWORD=tu_password_seguro

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=tu-dominio.com,www.tu-dominio.com
SANCTUM_EXPIRATION=1440
SANCTUM_TOKEN_PREFIX=sunat_
SANCTUM_VERIFY_IP=false
SANCTUM_LOG_USAGE=true
SANCTUM_MAX_INACTIVITY=120
SANCTUM_MAX_TOKENS=10
SANCTUM_ROTATE_TOKENS=false
SANCTUM_PURGE_DAYS=7

# SUNAT Configuration
SUNAT_ENVIRONMENT=beta
SUNAT_CERTIFICATE_PATH=
SUNAT_CERTIFICATE_PASSWORD=
SUNAT_PRIVATE_KEY_PATH=

# Files Configuration
FILES_STORAGE_PATH=storage/app/sunat
FILES_MAX_SIZE=10240

VITE_APP_NAME="${APP_NAME}"

# Docker Configuration
APP_PORT=80
RUN_MIGRATIONS=false
```

**Nota importante**: Asegúrate de actualizar `SANCTUM_STATEFUL_DOMAINS` con tu dominio real en producción.

### 4. Generar APP_KEY

Antes del primer despliegue, genera una clave de aplicación:

```bash
php artisan key:generate
```

O ejecuta este comando en el contenedor después del despliegue:

```bash
docker exec -it api-facturacion-app php artisan key:generate
```

### 5. Configurar Certificados SUNAT

1. Sube tus certificados a `storage/certificates/`
2. Configura las rutas en las variables de entorno o en la base de datos después del despliegue

### 6. Ejecutar Migraciones

Después del primer despliegue, ejecuta las migraciones:

```bash
docker exec -it api-facturacion-app php artisan migrate --force
```

O desde Coolify, puedes ejecutar comandos en el contenedor.

### 7. Configurar Permisos de Storage

Asegúrate de que los directorios de storage tengan los permisos correctos:

```bash
docker exec -it api-facturacion-app chmod -R 775 storage bootstrap/cache
docker exec -it api-facturacion-app chown -R www-data:www-data storage bootstrap/cache
```

## 🔄 Comandos Útiles

### Ver logs
```bash
docker logs -f api-facturacion-app
docker logs -f api-facturacion-nginx
docker logs -f api-facturacion-postgres
```

### Acceder al contenedor
```bash
docker exec -it api-facturacion-app bash
```

### Ejecutar comandos Artisan
```bash
docker exec -it api-facturacion-app php artisan migrate
docker exec -it api-facturacion-app php artisan cache:clear
docker exec -it api-facturacion-app php artisan config:clear
```

### Reiniciar servicios
```bash
docker-compose restart
```

## 📝 Notas Importantes

1. **Base de Datos**: El servicio PostgreSQL se crea automáticamente con un volumen persistente. Los datos se mantendrán aunque reinicies los contenedores.

2. **Puertos**: Coolify manejará automáticamente el puerto externo. El puerto interno 80 está configurado en el docker-compose.yml.

3. **SSL/HTTPS**: Coolify puede configurar automáticamente certificados SSL con Let's Encrypt.

4. **Backups**: Configura backups regulares de la base de datos PostgreSQL usando el volumen `postgres_data`.

5. **Actualizaciones**: Para actualizar la aplicación:
   ```bash
   git pull
   docker-compose build
   docker-compose up -d
   docker exec -it api-facturacion-app php artisan migrate --force
   ```

## 🐛 Solución de Problemas

### Error de conexión a la base de datos
- Verifica que las variables de entorno `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` estén correctas
- Asegúrate de que el servicio `postgres` esté corriendo: `docker ps`

### Error 502 Bad Gateway
- Verifica que el servicio `app` esté corriendo
- Revisa los logs: `docker logs api-facturacion-app`

### Permisos de storage
- Ejecuta: `docker exec -it api-facturacion-app chmod -R 775 storage bootstrap/cache`

### Cache de configuración
- Limpia el cache: `docker exec -it api-facturacion-app php artisan config:clear`

## 📚 Recursos Adicionales

- [Documentación de Coolify](https://coolify.io/docs)
- [Documentación de Laravel](https://laravel.com/docs)
- [Documentación de PostgreSQL](https://www.postgresql.org/docs/)


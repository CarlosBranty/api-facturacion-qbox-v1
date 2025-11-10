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

### 2. Configurar Variables de Entorno

En la sección de **Environment Variables** de Coolify, configura las siguientes variables:

```env
# Aplicación
APP_NAME="API Facturación SUNAT"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_KEY=base64:tu-clave-generada-aqui

# Base de Datos PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=facturacion_sunat
DB_USERNAME=postgres
DB_PASSWORD=tu_password_seguro

# Puerto de la aplicación (Coolify lo manejará automáticamente)
APP_PORT=80

# Cache y Sesiones (opcional, para producción)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Mail (configurar según tu proveedor)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 3. Generar APP_KEY

Antes del primer despliegue, genera una clave de aplicación:

```bash
php artisan key:generate
```

O ejecuta este comando en el contenedor después del despliegue:

```bash
docker exec -it api-facturacion-app php artisan key:generate
```

### 4. Configurar Certificados SUNAT

1. Sube tus certificados a `storage/certificates/`
2. Configura las rutas en las variables de entorno o en la base de datos después del despliegue

### 5. Ejecutar Migraciones

Después del primer despliegue, ejecuta las migraciones:

```bash
docker exec -it api-facturacion-app php artisan migrate --force
```

O desde Coolify, puedes ejecutar comandos en el contenedor.

### 6. Configurar Permisos de Storage

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


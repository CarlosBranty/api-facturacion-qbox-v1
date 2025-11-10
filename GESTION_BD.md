# 🗄️ Guía de Gestión de Base de Datos PostgreSQL

Esta guía explica cómo gestionar la base de datos PostgreSQL en tu despliegue de Coolify.

## 📋 Acceso a la Base de Datos

### Opción 1: Desde el Contenedor PostgreSQL

Puedes acceder directamente al contenedor de PostgreSQL:

```bash
# Acceder al contenedor
docker exec -it api-facturacion-postgres psql -U postgres -d facturacion_sunat

# O simplemente al servidor PostgreSQL
docker exec -it api-facturacion-postgres psql -U postgres
```

### Opción 2: Desde el Contenedor de la Aplicación

También puedes usar el contenedor de la aplicación para ejecutar comandos:

```bash
# Acceder al contenedor de la app
docker exec -it api-facturacion-app bash

# Desde dentro, puedes usar artisan para gestionar la BD
php artisan migrate
php artisan db:seed
php artisan tinker
```

### Opción 3: Herramientas GUI (Recomendado)

Puedes usar herramientas gráficas como **pgAdmin**, **DBeaver**, o **TablePlus**:

#### Configuración de Conexión:
- **Host**: `tu-servidor-coolify.com` (o la IP del servidor)
- **Puerto**: `5432` (si está expuesto) o usa un túnel SSH
- **Base de datos**: `facturacion_sunat`
- **Usuario**: `postgres`
- **Contraseña**: La que configuraste en `DB_PASSWORD`

#### Exponer Puerto PostgreSQL (Temporal)

Si necesitas acceso externo, puedes exponer el puerto temporalmente en `docker-compose.yml`:

```yaml
postgres:
  # ... otras configuraciones ...
  ports:
    - "5432:5432"  # Solo para desarrollo/testing
```

⚠️ **Importante**: No expongas el puerto en producción sin protección. Usa un túnel SSH o VPN.

## 🔧 Comandos Útiles

### Migraciones de Laravel

```bash
# Ejecutar migraciones
docker exec -it api-facturacion-app php artisan migrate

# Ejecutar migraciones con force (producción)
docker exec -it api-facturacion-app php artisan migrate --force

# Ver estado de migraciones
docker exec -it api-facturacion-app php artisan migrate:status

# Revertir última migración
docker exec -it api-facturacion-app php artisan migrate:rollback
```

### Seeders

```bash
# Ejecutar seeders
docker exec -it api-facturacion-app php artisan db:seed

# Ejecutar seeder específico
docker exec -it api-facturacion-app php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Backup y Restore

#### Crear Backup

```bash
# Backup completo
docker exec -it api-facturacion-postgres pg_dump -U postgres facturacion_sunat > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup comprimido
docker exec -it api-facturacion-postgres pg_dump -U postgres -Fc facturacion_sunat > backup_$(date +%Y%m%d_%H%M%S).dump
```

#### Restaurar Backup

```bash
# Desde archivo SQL
docker exec -i api-facturacion-postgres psql -U postgres facturacion_sunat < backup.sql

# Desde archivo comprimido
docker exec -i api-facturacion-postgres pg_restore -U postgres -d facturacion_sunat < backup.dump
```

### Consultas SQL Directas

```bash
# Ejecutar consulta SQL
docker exec -it api-facturacion-postgres psql -U postgres -d facturacion_sunat -c "SELECT * FROM companies LIMIT 10;"

# Modo interactivo
docker exec -it api-facturacion-postgres psql -U postgres -d facturacion_sunat
```

## 📊 Monitoreo y Estadísticas

### Ver Tamaño de la Base de Datos

```sql
SELECT 
    pg_size_pretty(pg_database_size('facturacion_sunat')) AS database_size;
```

### Ver Tablas y Tamaños

```sql
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
```

### Ver Conexiones Activas

```sql
SELECT 
    pid,
    usename,
    application_name,
    client_addr,
    state,
    query
FROM pg_stat_activity
WHERE datname = 'facturacion_sunat';
```

## 🔐 Seguridad

### Cambiar Contraseña de PostgreSQL

1. Accede al contenedor:
```bash
docker exec -it api-facturacion-postgres psql -U postgres
```

2. Cambia la contraseña:
```sql
ALTER USER postgres WITH PASSWORD 'nueva_contraseña_segura';
```

3. Actualiza la variable de entorno `DB_PASSWORD` en Coolify

4. Reinicia los contenedores

### Crear Usuario Específico (Recomendado)

```sql
-- Crear usuario
CREATE USER facturacion_user WITH PASSWORD 'contraseña_segura';

-- Dar permisos
GRANT ALL PRIVILEGES ON DATABASE facturacion_sunat TO facturacion_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO facturacion_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO facturacion_user;

-- Para tablas futuras
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO facturacion_user;
```

Luego actualiza en Coolify:
- `DB_USERNAME=facturacion_user`
- `DB_PASSWORD=contraseña_segura`

## 🔄 Mantenimiento

### Vacuum y Análisis

```bash
# Vacuum completo
docker exec -it api-facturacion-postgres psql -U postgres -d facturacion_sunat -c "VACUUM ANALYZE;"

# Vacuum de una tabla específica
docker exec -it api-facturacion-postgres psql -U postgres -d facturacion_sunat -c "VACUUM ANALYZE companies;"
```

### Ver Logs de PostgreSQL

```bash
docker logs -f api-facturacion-postgres
```

## 🚨 Solución de Problemas

### Error: "database does not exist"

```bash
# Crear la base de datos
docker exec -it api-facturacion-postgres psql -U postgres -c "CREATE DATABASE facturacion_sunat;"
```

### Error: "password authentication failed"

Verifica las variables de entorno en Coolify:
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`

### Reiniciar Base de Datos

```bash
# Detener contenedor
docker stop api-facturacion-postgres

# Iniciar contenedor
docker start api-facturacion-postgres
```

### Ver Variables de Entorno del Contenedor

```bash
docker exec api-facturacion-postgres env | grep POSTGRES
```

## 📝 Notas Importantes

1. **Backups Regulares**: Configura backups automáticos usando cron o herramientas de Coolify
2. **Volúmenes Persistentes**: Los datos se guardan en el volumen `postgres_data`, no se pierden al reiniciar
3. **Variables de Entorno**: Siempre actualiza las variables en Coolify, no directamente en el contenedor
4. **Producción**: Usa usuarios específicos con permisos limitados, no el usuario `postgres`

## 🔗 Recursos Adicionales

- [Documentación de PostgreSQL](https://www.postgresql.org/docs/)
- [Laravel Database](https://laravel.com/docs/database)
- [Coolify Documentation](https://coolify.io/docs)


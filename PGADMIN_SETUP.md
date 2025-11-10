# 🗄️ Configuración de pgAdmin

Esta guía explica cómo usar pgAdmin para gestionar tu base de datos PostgreSQL.

## 🚀 Acceso a pgAdmin

### En Coolify

1. **Configurar dominio para pgAdmin**:
   - Ve a tu aplicación en Coolify
   - Agrega un nuevo dominio para el servicio `pgadmin`
   - Por ejemplo: `pgadmin.tu-dominio.com`
   - Coolify detectará automáticamente el servicio por la label `coolify.name=pgadmin`

2. **Acceder a pgAdmin**:
   - Abre tu navegador en: `https://pgadmin.tu-dominio.com`
   - Email: `admin@facturacion.com` (o el que configuraste en `PGADMIN_EMAIL`)
   - Contraseña: `admin` (o la que configuraste en `PGADMIN_PASSWORD`)

### Acceso Local (si expones el puerto)

Si descomentaste el puerto en `docker-compose.yml`:
- URL: `http://localhost:5050`
- Credenciales: Las mismas de arriba

## 🔧 Configurar Conexión a PostgreSQL

Una vez dentro de pgAdmin:

1. **Clic derecho en "Servers"** → **Register** → **Server**

2. **Pestaña "General"**:
   - Name: `Facturación SUNAT` (o el nombre que prefieras)

3. **Pestaña "Connection"**:
   - Host name/address: `postgres` (nombre del servicio en docker-compose)
   - Port: `5432`
   - Maintenance database: `facturacion_sunat`
   - Username: `postgres` (o el de `DB_USERNAME`)
   - Password: La contraseña de `DB_PASSWORD`
   - ✅ Marca "Save password"

4. **Clic en "Save"**

## 📊 Funcionalidades de pgAdmin

### Ver Tablas

1. Expande tu servidor → Databases → `facturacion_sunat` → Schemas → public → Tables
2. Verás todas las tablas de Laravel

### Ejecutar Consultas SQL

1. Clic derecho en la base de datos → **Query Tool**
2. Escribe tu consulta SQL
3. Clic en el botón de ejecutar (▶️) o presiona F5

### Ver Datos de una Tabla

1. Clic derecho en una tabla → **View/Edit Data** → **All Rows**
2. Puedes editar datos directamente desde la interfaz

### Crear Backups

1. Clic derecho en la base de datos → **Backup...**
2. Selecciona el formato (Custom, Plain, etc.)
3. Elige la ubicación y nombre del archivo
4. Clic en "Backup"

### Restaurar Backups

1. Clic derecho en la base de datos → **Restore...**
2. Selecciona el archivo de backup
3. Configura las opciones si es necesario
4. Clic en "Restore"

### Ver Estadísticas

1. Clic derecho en la base de datos → **Statistics**
2. Verás información sobre:
   - Tamaño de la base de datos
   - Número de tablas
   - Espacio utilizado
   - Y más

## 🔐 Cambiar Credenciales de pgAdmin

Para cambiar el email y contraseña de pgAdmin:

1. **En Coolify**, actualiza las variables de entorno:
   ```env
   PGADMIN_EMAIL=nuevo@email.com
   PGADMIN_PASSWORD=nueva_contraseña_segura
   ```

2. **Reinicia el contenedor**:
   ```bash
   docker restart api-facturacion-pgadmin
   ```

## 🛠️ Solución de Problemas

### No puedo conectarme a PostgreSQL desde pgAdmin

**Problema**: Error "could not connect to server"

**Solución**:
1. Verifica que el servicio `postgres` esté corriendo:
   ```bash
   docker ps | grep postgres
   ```

2. Verifica que estés usando `postgres` como hostname (no `localhost`)

3. Verifica las credenciales en las variables de entorno:
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `DB_DATABASE`

### pgAdmin no carga

**Problema**: La página no carga o muestra error 502

**Solución**:
1. Verifica que el contenedor esté corriendo:
   ```bash
   docker logs api-facturacion-pgadmin
   ```

2. Verifica que el dominio esté configurado correctamente en Coolify

3. Reinicia el contenedor:
   ```bash
   docker restart api-facturacion-pgadmin
   ```

### Olvidé la contraseña de pgAdmin

**Solución**:
1. Accede al contenedor:
   ```bash
   docker exec -it api-facturacion-pgadmin bash
   ```

2. O simplemente actualiza las variables de entorno en Coolify y reinicia

## 📝 Notas Importantes

1. **Seguridad**: 
   - Cambia las credenciales por defecto en producción
   - No expongas pgAdmin públicamente sin autenticación adicional
   - Considera usar VPN o acceso restringido por IP

2. **Volúmenes**: 
   - Los datos de pgAdmin (configuraciones, conexiones guardadas) se guardan en el volumen `pgadmin_data`
   - No se perderán al reiniciar el contenedor

3. **Rendimiento**: 
   - pgAdmin puede consumir recursos, especialmente con bases de datos grandes
   - Considera desactivarlo en producción si no lo necesitas constantemente

## 🔗 Recursos Adicionales

- [Documentación de pgAdmin](https://www.pgadmin.org/docs/)
- [Guía de Usuario de pgAdmin](https://www.pgadmin.org/docs/pgadmin4/latest/index.html)


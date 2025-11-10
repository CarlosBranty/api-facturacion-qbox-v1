# 🔧 Solución de Problemas - Docker Build

## Error: `composer install` falla con exit code 2

Si encuentras este error durante el build de Docker, aquí hay varias soluciones:

### Solución 1: Verificar extensiones PHP

El error puede deberse a extensiones PHP faltantes. Verifica que todas estas extensiones estén instaladas:

```bash
php -m | grep -E "(pdo|pgsql|mbstring|gd|zip|xml|openssl)"
```

### Solución 2: Limpiar cache de Composer

Si el problema persiste, intenta limpiar el cache de Composer antes del build:

```bash
docker build --no-cache --progress=plain -t api-facturacion .
```

### Solución 3: Instalar dependencias localmente primero

1. Instala las dependencias localmente:
```bash
composer install --no-dev --optimize-autoloader
```

2. Luego construye la imagen Docker:
```bash
docker build -t api-facturacion .
```

### Solución 4: Dockerfile alternativo (con más debugging)

Si el problema persiste, puedes usar este Dockerfile alternativo que muestra más información:

```dockerfile
FROM php:8.2-fpm

# ... (resto del Dockerfile)

# Instalar dependencias con más información de debug
RUN set -eux; \
    echo "📦 Verificando Composer..."; \
    composer --version; \
    echo "📦 Verificando extensiones PHP..."; \
    php -m; \
    echo "📦 Instalando dependencias..."; \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts -vvv
```

### Solución 5: Problemas de memoria

Si el error es por falta de memoria, aumenta el límite de memoria de PHP:

```dockerfile
# Agregar antes de composer install
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
```

### Solución 6: Verificar composer.lock

Asegúrate de que el `composer.lock` esté actualizado:

```bash
composer update --lock
git add composer.lock
git commit -m "Update composer.lock"
```

### Solución 7: Build en etapas (multi-stage)

Si el problema persiste, puedes usar un build multi-stage:

```dockerfile
# Stage 1: Instalar dependencias
FROM composer:latest AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Stage 2: Imagen final
FROM php:8.2-fpm
# ... (resto de la configuración)
COPY --from=composer-stage /app/vendor /var/www/html/vendor
```

## Errores Comunes

### Error: "Your requirements could not be resolved"

**Causa**: Conflicto de versiones en `composer.json` o `composer.lock` desactualizado.

**Solución**:
```bash
composer update --no-dev
composer dump-autoload --optimize
```

### Error: "The requested PHP extension X is missing"

**Causa**: Falta una extensión PHP requerida.

**Solución**: Agrega la extensión al Dockerfile en la sección de instalación de extensiones.

### Error: "Out of memory"

**Causa**: Memoria insuficiente durante la instalación.

**Solución**: Aumenta el límite de memoria o usa `--no-scripts` durante la instalación.

## Verificar el Build

Para ver más detalles del error, construye con más verbosidad:

```bash
docker build --progress=plain --no-cache -t api-facturacion . 2>&1 | tee build.log
```

Esto guardará todos los logs en `build.log` para que puedas revisarlos.

## Contacto

Si ninguna de estas soluciones funciona, verifica:
1. Los logs completos del build
2. La versión de Composer
3. Las extensiones PHP instaladas
4. El contenido de `composer.json` y `composer.lock`


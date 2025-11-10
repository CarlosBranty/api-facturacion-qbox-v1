FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configurar límite de memoria para Composer
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Instalar extensiones PHP necesarias
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache \
    && docker-php-ext-enable opcache

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de configuración
COPY composer.json composer.lock ./

# Instalar dependencias de PHP (sin dev para producción)
# Instalar sin scripts primero, luego ejecutaremos los scripts después de copiar los archivos
RUN set -eux; \
    echo "📦 Verificando Composer..."; \
    composer --version; \
    echo "📦 Verificando extensiones PHP..."; \
    php -m; \
    echo "📦 Instalando dependencias..."; \
    COMPOSER_MEMORY_LIMIT=512M composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts || \
    (echo "⚠️ Primera instalación falló, intentando con más verbosidad..." && \
     COMPOSER_MEMORY_LIMIT=512M composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts -vvv || \
     (echo "❌ Error en composer install. Diagnóstico:" && \
      composer diagnose && \
      exit 1))

# Copiar el resto de la aplicación
COPY . .

# Ejecutar scripts de composer después de copiar todos los archivos
RUN composer dump-autoload --optimize --no-interaction

# Copiar script de entrada
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configurar PHP-FPM para Nginx
RUN sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 9000/' /usr/local/etc/php-fpm.d/www.conf

# Exponer puerto
EXPOSE 9000

# Script de entrada
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Comando por defecto
CMD ["php-fpm"]


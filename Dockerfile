FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de configuración
COPY composer.json composer.lock ./

# Instalar dependencias de PHP (sin dev para producción)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copiar el resto de la aplicación
COPY . .

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


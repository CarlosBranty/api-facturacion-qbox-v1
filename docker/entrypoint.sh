#!/bin/bash
set -e

echo "🚀 Iniciando aplicación Laravel..."

# Esperar a que PostgreSQL esté listo
echo "⏳ Esperando a que PostgreSQL esté disponible..."
until php -r "try { \$pdo = new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'OK'; exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
  echo "PostgreSQL no está disponible aún, esperando..."
  sleep 2
done

echo "✅ PostgreSQL está disponible"

# Ejecutar migraciones si es necesario
if [ "$RUN_MIGRATIONS" = "true" ]; then
  echo "📦 Ejecutando migraciones..."
  php artisan migrate --force
fi

# Limpiar cache
echo "🧹 Limpiando cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
if [ "$APP_ENV" = "production" ]; then
  echo "⚡ Optimizando para producción..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

# Configurar permisos
echo "🔐 Configurando permisos..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ Aplicación lista!"

# Ejecutar el comando original (php-fpm)
exec "$@"


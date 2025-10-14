#!/bin/sh
set -e

# Cria o diretório para o socket do PHP-FPM
mkdir -p /run/php

# Garante diretório temporário gravável para uploads
mkdir -p /tmp
chmod 1777 /tmp

# Ajusta permissões das pastas do Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Instala as dependências do Composer
composer install --no-interaction --optimize-autoloader --no-dev

# Roda as migrações do banco de dados
php artisan migrate --force

# Garante que o link simbólico de storage exista
php artisan storage:link || true

# Inicia o Supervisor, que gerencia o Nginx e o PHP-FPM
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

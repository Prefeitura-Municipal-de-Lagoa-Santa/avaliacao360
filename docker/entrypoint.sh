#!/bin/sh
set -e

# Cria o diretório para o socket do PHP-FPM
mkdir -p /run/php

# Ajusta permissões das pastas do Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Instala as dependências do Composer
composer install --no-interaction --optimize-autoloader --no-dev

# Roda as migrações do banco de dados
php artisan migrate --force

# Inicia o Supervisor, que gerencia o Nginx e o PHP-FPM
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf

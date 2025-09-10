#!/bin/sh
echo "🎬 entrypoint.sh: [$(whoami)] [PHP $(php -r 'echo phpversion();')]"
composer install
echo "🎬 artisan commands"
php artisan migrate --no-interaction --force
echo "🎬 start supervisord"
supervisord -c /etc/supervisor/supervisord.conf

#!/bin/sh
set -e
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true
PORT="${PORT:-80}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
if [ -d /var/www/html/uploads-seed ]; then
    cp -rn /var/www/html/uploads-seed/. /var/www/html/kultoura/assets/uploads/ 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/kultoura/assets/uploads
fi
exec apache2-foreground

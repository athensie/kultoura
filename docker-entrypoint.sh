#!/bin/sh
set -e

# Known Railway-specific issue: php:8.2-apache can end up with
# mpm_event/mpm_worker re-enabled alongside mpm_prefork by the time the
# image actually runs on Railway's infrastructure, even after verifying
# they were disabled at Docker build time (AH00534: "More than one MPM
# loaded"). Redoing it here, immediately before Apache starts, is the
# documented fix — it operates on the real running container instead of
# only the build layer.
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Railway assigns a dynamic $PORT and expects the app to listen on it —
# Apache's image defaults to a fixed port 80, so both spots that name
# the port need patching before apache2-foreground starts.
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Restore any seed images missing from assets/uploads/ — on Railway
# that path is a persistent volume, which starts out empty the first
# time it's attached (hiding the placeholder images baked into the
# image at build time). -n (no-clobber) means this only ever fills in
# what's missing; it never overwrites a real upload that's there.
if [ -d /var/www/html/uploads-seed ]; then
    cp -rn /var/www/html/uploads-seed/. /var/www/html/kultoura/assets/uploads/ 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/kultoura/assets/uploads
fi

exec apache2-foreground

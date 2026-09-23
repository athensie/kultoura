#!/bin/sh
# Railway assigns a dynamic $PORT and expects the app to listen on it —
# Apache's image defaults to a fixed port 80, so both spots that name
# the port need patching before apache2-foreground starts.
set -e

PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground

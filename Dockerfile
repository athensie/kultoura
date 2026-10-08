FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
             /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
             /etc/apache2/mods-enabled/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && grep -rl FOREGROUND /etc/apache2/ || true \
    && apache2ctl -D FOREGROUND -t

# The base image's default vhost has AllowOverride None, which would
# silently ignore assets/uploads/.htaccess (the block-script-execution
# defense-in-depth for uploaded files) on Railway even though it works
# locally under XAMPP's default config.
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# The app is served under /kultoura everywhere it already runs (local
# XAMPP htdocs, the athensie/kultoura GitHub Pages-style layout), and
# every page's nav/links/BASE_URL is hardcoded to that path. Keeping
# the same subpath in the container avoids rewriting those links —
# the site is reached at https://<railway-domain>/kultoura/.
COPY . /var/www/html/kultoura/

# Snapshot of assets/uploads/ exactly as it exists in the image — the
# handful of seed/placeholder images committed to git. Kept outside the
# mount path so docker-entrypoint.sh can restore any of them missing
# from the persistent volume mounted at assets/uploads/ (e.g. on first
# attach, when the volume starts empty) without ever touching a real
# upload that's already there.
RUN cp -r /var/www/html/kultoura/assets/uploads /var/www/html/uploads-seed

# Bare-domain visits ("/") land somewhere real instead of a 404.
RUN printf '<?php header("Location: /kultoura/"); exit;\n' > /var/www/html/index.php

# Railway's persistent volume mounts here (see docker-entrypoint.sh for
# the seed-restore step) so admin- and user-uploaded photos survive
# redeploys instead of living on the container's throwaway filesystem.
RUN mkdir -p /var/www/html/kultoura/assets/uploads \
    && chown -R www-data:www-data /var/www/html/kultoura/assets/uploads

# Must NOT be excluded in .dockerignore — this COPY needs it in the build context.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

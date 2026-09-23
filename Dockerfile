FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# The app is served under /kultoura everywhere it already runs (local
# XAMPP htdocs, the athensie/kultoura GitHub Pages-style layout), and
# every page's nav/links/BASE_URL is hardcoded to that path. Keeping
# the same subpath in the container avoids rewriting those links —
# the site is reached at https://<railway-domain>/kultoura/.
COPY . /var/www/html/kultoura/

# Bare-domain visits ("/") land somewhere real instead of a 404.
RUN printf '<?php header("Location: /kultoura/"); exit;\n' > /var/www/html/index.php

# Railway's uploads volume (if configured) mounts here — see the
# deploy notes for adding a persistent volume at this path so admin-
# uploaded photos survive redeploys.
RUN mkdir -p /var/www/html/kultoura/assets/uploads \
    && chown -R www-data:www-data /var/www/html/kultoura/assets/uploads

# Must NOT be excluded in .dockerignore — this COPY needs it in the build context.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

FROM php:8.4-apache-bookworm@sha256:5d55c49594dd833e2e4ff677b65f7d8ff48817f443a60691d9b2b382413bba42 AS app
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev libonig-dev libsodium-dev msmtp ca-certificates \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 gd pdo_pgsql exif mbstring sodium \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/camagru.ini
COPY docker/mail/msmtprc /etc/msmtprc
COPY docker/apache/log.sh /usr/local/bin/camagru-apache-log
COPY . .
RUN chmod 755 /usr/local/bin/camagru-apache-log && php scripts/overlays.php && mkdir -p storage/media storage/tmp storage/sessions storage/logs \
    && chown -R www-data:www-data storage
CMD ["apache2-foreground"]

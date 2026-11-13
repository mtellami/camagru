FROM php:8.4-fpm-bookworm@sha256:b75642e8c89c321039f351466134df951576fb0f7017117b3931e9a19b908d6c
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev libonig-dev libsodium-dev msmtp ca-certificates nginx curl \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 gd pdo_pgsql exif mbstring sodium \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY tools/docker/php/php.ini /usr/local/etc/php/conf.d/camagru.ini
COPY tools/docker/php/fpm.conf /usr/local/etc/php-fpm.d/zzz-camagru.conf
COPY tools/docker/mail/msmtprc /etc/msmtprc
COPY tools/docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY tools/docker/start-web.sh /usr/local/bin/start-web
COPY . .
RUN chmod +x /usr/local/bin/start-web \
    && mkdir -p storage/media storage/tmp storage/sessions storage/logs \
    && chown -R www-data:www-data storage
EXPOSE 80
CMD ["start-web"]

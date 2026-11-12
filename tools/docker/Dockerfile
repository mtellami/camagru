FROM php:8.4-fpm-bookworm@sha256:b75642e8c89c321039f351466134df951576fb0f7017117b3931e9a19b908d6c AS app
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev libonig-dev libsodium-dev msmtp ca-certificates \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 gd pdo_pgsql exif mbstring sodium \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY tools/docker/php/php.ini /usr/local/etc/php/conf.d/camagru.ini
COPY tools/docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-camagru.conf
COPY tools/docker/mail/msmtprc /etc/msmtprc
COPY . .
RUN php tools/scripts/overlays.php && mkdir -p storage/media storage/tmp storage/sessions storage/logs \
    && chown -R www-data:www-data storage
CMD ["php-fpm"]

FROM nginx:stable-alpine@sha256:583b7f2fce1cdb8da5ad5c39ebd3c33b98b87d493190e548bdee1fa8c619c311 AS nginx
ENV NGINX_ENTRYPOINT_QUIET_LOGS=1
COPY tools/docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=app /var/www/html/public/assets /var/www/html/public/assets

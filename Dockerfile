# OOTD: one image for the web server and the background worker
#   docker compose up -d            (see docker-compose.yml and docs/SELF-HOSTING.md)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip

FROM php:8.3-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!' /etc/apache2/apache2.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf "upload_max_filesize=20M\npost_max_size=24M\nexpose_php=Off\n" > "$PHP_INI_DIR/conf.d/ootd.ini" \
 && printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-enabled/ootd.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
# styles are compiled once here instead of on requests (CSS_BUILT tells init.php)
RUN mkdir -p Assets/stylesheets/css \
 && php -d display_errors=stderr -d error_reporting=0 vendor/bin/pscss --style=compressed --load-path=Assets/stylesheets/sass \
      Assets/stylesheets/sass/main.scss > Assets/stylesheets/css/main.css \
 && test -s Assets/stylesheets/css/main.css \
 && rm -rf .github tests \
 && mkdir -p storage tmp && chown -R www-data:www-data storage tmp

ENV CSS_BUILT=1
VOLUME ["/var/www/html/storage"]
HEALTHCHECK --interval=1m --timeout=5s --start-period=1m CMD php -r 'exit(@file_get_contents("http://localhost/health") === false ? 1 : 0);'
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]

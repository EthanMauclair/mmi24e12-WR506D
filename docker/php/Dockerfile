# Image PHP-FPM 8.2 pour l'API Symfony 7.4 (environnement de développement).
FROM php:8.2-fpm-alpine

# Installeur d'extensions PHP : gère les dépendances système de chaque extension.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        apcu \
        intl \
        opcache \
        pdo_mysql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /var/www/backend

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

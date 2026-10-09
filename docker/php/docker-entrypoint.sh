#!/bin/sh
set -e

# Au premier démarrage, installe les dépendances PHP si vendor/ est absent.
if [ "$1" = 'php-fpm' ] && [ ! -f vendor/autoload_runtime.php ]; then
    composer install --prefer-dist --no-progress --no-interaction
fi

exec docker-php-entrypoint "$@"

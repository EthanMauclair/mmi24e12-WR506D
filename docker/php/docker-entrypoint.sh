#!/bin/sh
set -e

# Installe les dépendances PHP au premier démarrage (vendor/ n'est pas versionné)
if [ ! -f vendor/autoload_runtime.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Applique les migrations en attente
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"

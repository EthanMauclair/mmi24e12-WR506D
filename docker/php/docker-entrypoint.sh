#!/bin/sh
set -e

# Installe les dépendances PHP au premier démarrage (vendor/ n'est pas versionné)
if [ ! -f vendor/autoload_runtime.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Applique les migrations en attente
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# PHP-FPM sert les requêtes en www-data, alors que ce script et « docker compose exec » tournent en root :
# les ACL donnent l'écriture aux deux, y compris sur les fichiers créés plus tard (cache, limites d'appels, photos)
mkdir -p var/cache var/log var/share public/uploads
setfacl -R -m u:www-data:rwX -m u:root:rwX var
setfacl -dR -m u:www-data:rwX -m u:root:rwX var
# Le dossier des photos peut être sur un montage qui ne gère pas les ACL (Docker Desktop) : sans incidence
setfacl -R -m u:www-data:rwX -m u:root:rwX public/uploads 2>/dev/null || true
setfacl -dR -m u:www-data:rwX -m u:root:rwX public/uploads 2>/dev/null || true

exec "$@"

#!/bin/sh
# Writes .env from the container's environment and waits for the database. The web container
# (OOTD_ROLE=web, the default) then installs OOTD or upgrades it to this image's schema; the worker
# waits until that's done.
set -e
cd /var/www/html

if [ -z "$PWD_SEED" ]; then
    echo "PWD_SEED must be set, and never changed once people have accounts: openssl rand -hex 32" >&2
    exit 1
fi

rm -f .env
php bin/ootd env

echo "waiting for the database at ${DB_HOST}"
i=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_SCHEMA"), getenv("DB_USER"), getenv("DB_PASS")); } catch (Exception $e) { exit(1); }'; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then echo "database not reachable" >&2; exit 1; fi
    sleep 2
done

if [ "${OOTD_ROLE:-web}" = "web" ]; then
    php bin/ootd install
else
    until php bin/ootd health | grep -q "^database  ok"; do
        echo "waiting for the web container to install or upgrade the database"
        sleep 5
    done
fi

if [ "$(id -u)" = "0" ]; then
    chown www-data:www-data .env tmp storage
    chown -R www-data:www-data tmp storage
fi
exec "$@"

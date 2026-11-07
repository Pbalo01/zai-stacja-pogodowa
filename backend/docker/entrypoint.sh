#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ]; then
    # Baza (np. Neon) może się jeszcze wybudzać – czekamy maksymalnie ok. 60 s
    tries=0
    until php bin/console dbal:run-sql -q "SELECT 1" > /dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "Baza danych niedostępna – przerywam start." >&2
            exit 1
        fi
        sleep 2
    done

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    # Konto administratora z env (komenda nie zmienia istniejącego konta)
    if [ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_PASSWORD" ]; then
        php bin/console app:create-admin --no-interaction
    fi
fi

exec "$@"

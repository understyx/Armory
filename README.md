# Warmane Armory

A Symfony 7.3 application for viewing and caching Warmane character data, including gear, talents, gems, enchants, professions, and match details.

## Requirements

- PHP 8.2–8.4 with the extensions required by Composer and the PDO driver for your database
- Composer 2
- PostgreSQL 16 by default (the Doctrine configuration also supports SQLite for local development)
- A web server whose document root points to `public/`

## Local setup

```bash
composer install
cp .env .env.local
# Edit .env.local with local database and Discord OAuth values.
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:import-items
symfony server:start
```

The item importer reads `data/items.sql` by default. A different SQL dump can be passed as its first argument.

## Production deployment

1. Clone the repository and configure the web server to serve the `public/` directory.
2. Set these environment variables outside Git:
   - `APP_ENV=prod`
   - `APP_DEBUG=0`
   - `APP_SECRET` to a long random value
   - `DEFAULT_URI` to the public HTTPS origin
   - `DATABASE_URL` to the production database DSN
   - `OAUTH_DISCORD_ID` and `OAUTH_DISCORD_SECRET`
   - `MESSENGER_TRANSPORT_DSN` and `MAILER_DSN` if their defaults are not suitable
3. Register `https://your-domain.example/connect/discord/check` as the Discord OAuth redirect URL.
4. Install and initialize the application:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:import-items
php bin/console asset-map:compile
php bin/console cache:clear
```

Ensure the web-server user can write to `var/`. Run `php bin/console app:purge-snapshots` periodically if old character snapshots should be removed automatically.

## Verification

```bash
composer validate --no-check-publish
php bin/phpunit
php bin/console lint:container
php bin/console lint:twig templates
```

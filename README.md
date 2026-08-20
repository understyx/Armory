# Warmane Armory

A Symfony 7.3 application for viewing and caching Warmane character data, including gear, talents, gems, enchants, professions, and match details.

## Requirements

- PHP 8.2 or newer with the extensions required by Composer, including `pdo_mysql`
- Composer 2
- MariaDB 10.6+ or MySQL 8
- A web server whose document root points to `public/`

## Local setup

Run the interactive installer and accept the defaults to use the bundled MariaDB configuration:

```bash
bin/install
symfony server:start
```

The installer prompts for the database connection, application URL, and optional Discord OAuth credentials. It can start the bundled MariaDB container, installs PHP dependencies, creates the database, runs migrations, and optionally imports the item data. It stores local values in `.env.local`, which is ignored by Git.

The default database block is:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=raidbot
DB_PASSWORD=raidbot
DB_NAME=raidbot
```

The item importer reads `data/items.sql` by default. A different SQL dump can be passed as its first argument to `php bin/console app:import-items`.

## Production deployment

1. Clone the repository and configure the web server to serve the `public/` directory.
2. Set these environment variables outside Git:
   - `APP_ENV=prod`
   - `APP_DEBUG=0`
   - `APP_SECRET` to a long random value
   - `DEFAULT_URI` to the public HTTPS origin
   - `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` for MariaDB/MySQL
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

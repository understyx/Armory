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

### Automated Ubuntu/Debian installation

Run the server installer as root from an existing checkout. It also works as a
standalone script copied to a fresh Ubuntu or Debian server. The SSH agent used
for the GitHub checkout must be available to `sudo`:

```bash
sudo --preserve-env=SSH_AUTH_SOCK \
  SERVER_NAME=armory.example.com \
  APP_URL=https://armory.example.com \
  TLS_CERTIFICATE=/etc/letsencrypt/live/example.com/fullchain.pem \
  TLS_CERTIFICATE_KEY=/etc/letsencrypt/live/example.com/privkey.pem \
  bash bin/install-server
```

By default it creates the `armory` system account, checks out
`git@github.com:understyx/Armory.git` into `/var/www/Armory`, provisions an
`armory` MariaDB database and account, installs the application, and configures
Nginx with PHP-FPM. If `/var/www/Armory` is already a Git checkout, the installer
fetches `GIT_BRANCH` and applies a fast-forward update. It stops without changing
the checkout if tracked files have local modifications or the branch has
diverged. Ignored deployment files such as `.env.local` are preserved.

Configuration can be overridden with environment variables including
`DEPLOY_DIR`, `GIT_BRANCH`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `SERVER_NAME`,
`APP_URL`, `TLS_CERTIFICATE`, `TLS_CERTIFICATE_KEY`, and `IMPORT_ITEMS=0`. The
default database password is `armory`; set a strong `DB_PASSWORD` for an
internet-facing deployment. `SERVER_NAME` must be the site's exact hostname so
that Nginx selects it ahead of any wildcard virtual host. It is derived from
`APP_URL` when only `APP_URL` is provided.

When both TLS paths are provided, the installer creates an exact-name HTTPS
virtual host and redirects HTTP to HTTPS. The certificate may be an existing
wildcard certificate, but it must cover `SERVER_NAME`. Without certificate paths,
the installer creates an HTTP-only virtual host and requires an `http://` URL.

### Manual installation

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

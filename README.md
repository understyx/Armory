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

For canonical item data and local tooltips, place a TrinityCore 3.3.5a full-world dump outside Git and run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:import-trinity-items /path/to/TDB_full_world_335.sql \
  --gear-score-file=data/items.sql
```

The importer streams `item_template` from the full dump, uses TrinityCore for authoritative item fields, and uses `data/items.sql` only as the precomputed GearScore overlay. Existing icons and GearScores without a matching overlay row are preserved. The older `app:import-items` command remains available as a lightweight fallback.

The raw TrinityCore dump is intentionally ignored by Git. Client DBC lookups are still required for exact meta-gem conditions; base item tooltips and ordinary socket matching work without them.

Special item effects and item-set text are enriched into local cache tables. Cavern of Time is queried first because it reflects original 3.3.5 data; Wowhead is a fallback only, since WotLK Classic changed some item and trinket effects. The character page never waits for either provider. Missing data is queued for the Messenger worker and appears on a later view.

Item, gem, and talent images load from the Wowhead or Warmane CDN first. If that request fails, the browser retries through a same-origin `/wow-icons/` URL. The server obtains the fallback from the available CDN, keeps an immutable copy in `var/wow-icons`, and serves that copy thereafter. Preserve that directory between deployments to retain the cached artwork.

Bulk prefilling is optional and must be requested explicitly:

```bash
php bin/console app:enrich-item-tooltips --all
```

To enrich one item or refresh cached text:

```bash
php bin/console app:enrich-item-tooltips 50363
php bin/console app:enrich-item-tooltips 50363 --force
```

Development environments must have an async worker running for lazy enrichment:

```bash
php bin/console messenger:consume async --time-limit=3600
```

Production installs made with `bin/install-server` configure this worker as the `armorystuff-messenger` systemd service. Raw provider responses and parser versions are retained so cached pages can be reparsed if an external site changes its markup. External providers only supply display text; TrinityCore remains authoritative for item, spell-trigger, and set IDs. Custom items can be populated directly in the same cache tables when external databases do not know them.

## Production deployment

### Automated Ubuntu/Debian installation

Run the server installer with `sudo` from an existing checkout. It also works as
a standalone script copied to a fresh Ubuntu or Debian server. The installer
automatically uses the invoking sudo user's `~/.ssh/id_ed25519` when present. A
different private key can be selected explicitly with `GIT_SSH_KEY`:

```bash
sudo \
  GIT_SSH_KEY=/home/ubuntu/.ssh/id_ed25519 \
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
After installing the locked Composer dependencies, every run applies all pending
Doctrine migrations before compiling assets and clearing the production cache.

The key's public half must be registered with GitHub, either on the user account
or in the repository's **Settings → Deploy keys** page. Read-only repository
access is sufficient. If no key is selected or auto-detected, Git uses the
invoking environment's normal SSH agent and configuration instead.

Configuration can be overridden with environment variables including
`DEPLOY_DIR`, `GIT_BRANCH`, `GIT_SSH_KEY`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`,
`SERVER_NAME`, `APP_URL`, `TLS_CERTIFICATE`, `TLS_CERTIFICATE_KEY`, and
`IMPORT_ITEMS=0`. The default database password is `armory`; set a strong
`DB_PASSWORD` for an internet-facing deployment. `SERVER_NAME` must be the site's
exact hostname so that Nginx selects it ahead of any wildcard virtual host. It is
derived from `APP_URL` when only `APP_URL` is provided.

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
php bin/console app:import-trinity-items /path/to/TDB_full_world_335.sql --gear-score-file=data/items.sql
php bin/console asset-map:compile
php bin/console cache:clear
```

Ensure the web-server user can write to `var/`. Run `php bin/console app:purge-snapshots` periodically if old character snapshots should be removed automatically.

## Public character API

Read the most recently cached character snapshot:

```http
GET /api/character/{name}/{realm}
```

The response contains `updatedAt`, basic character identity and progression data,
equipped item/enchant/transmog/gem IDs, structured professions, and talent strings.
This endpoint never contacts Warmane. It returns `404 Not Found` when no snapshot
has been cached yet.

Queue a fresh scrape with:

```http
POST /api/requestupdate/{name}/{realm}
```

Accepted requests return `202 Accepted`. A character may only be requested once
every five minutes; requests made during that window return `429 Too Many Requests`
with a `Retry-After` header. Refreshes are processed by the existing Messenger
worker, so production must keep `armorystuff-messenger` (or an equivalent
`messenger:consume async` process) running.

## Verification

```bash
composer validate --no-check-publish
php bin/phpunit
php bin/console lint:container
php bin/console lint:twig templates
```

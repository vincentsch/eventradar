# Event Visuals

Event Visuals is a Laravel 13 and Inertia 3 application for discovering global events in an
editorial grid or on a Mapbox map. MySQL is the source of truth, Meilisearch is a replaceable public
discovery index, and Redis/Horizon delivers attendance confirmations plus three-day and 24-hour
reminders.

The public site supports event-local dates and timezones, locally served galleries, location/date
filters, attendee accounts, and a personal event list. Administrators get a separate database-backed
dashboard, full event catalogue, CRUD, image uploads, address lookup, and attendee lists.

## Local setup with Sail

Requirements: Docker, Composer, and free ports matching `.env.example`.

```bash
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail artisan events:search-index
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The seeded administrator is `reviewer@example.test` with password `password`. Mailpit is available
on the configured `FORWARD_MAILPIT_DASHBOARD_PORT`. Horizon and the scheduler run as Compose
services, so confirmations and reminders work without extra terminals.

The default `dev` seed creates 10,000 deterministic events. For a quick reset or the explicit scale
profile:

```bash
EVENT_SEED_PROFILE=smoke ./vendor/bin/sail artisan migrate:fresh --seed
EVENT_SEED_PROFILE=full EVENT_SEED_ALLOW_FULL=true ./vendor/bin/sail artisan migrate:fresh --seed
```

Rebuild Meilisearch after replacing the catalogue. Ordinary admin edits enqueue a one-event
reconciliation and do not rebuild the full index.

Run the checks with:

```bash
./vendor/bin/sail artisan test
./vendor/bin/pint --test
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

See [docs/development.md](docs/development.md) for seed and database details.

## Environment

Start from `.env.example`. The important application-specific values are:

- `DB_*`: MySQL connection. MySQL 8 is the supported production database.
- `REDIS_*` and `QUEUE_CONNECTION=redis`: Horizon queues and scheduler locks.
- `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, and `MEILISEARCH_EVENT_INDEX`: server-side discovery.
- `VITE_MAPBOX_ACCESS_TOKEN`: URL-restricted public token used only by Mapbox GL JS.
- `MAPBOX_GEOCODING_TOKEN`: server-side token used for permanent forward/reverse geocoding.
- `MAIL_MAILER=postmark`, `POSTMARK_API_KEY`, and `POSTMARK_MESSAGE_STREAM_ID`: production mail.
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`: verified Postmark sender identity.

Uploaded event images use Laravel's `public` disk and require the `public/storage` symlink. The
server needs Imagick; uploads are decoded, stripped, resized, and re-encoded as local WebP files.

## Production with Forge

Create a normal Laravel site with its web root set to `public`, PHP 8.3 or newer, MySQL 8, Redis,
Imagick, and a reachable Meilisearch instance. Configure the environment above, then use a deploy
script along these lines:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan horizon:terminate
```

Run `php artisan events:search-index` once after the initial production dataset is loaded. Configure
Forge's scheduler to run `php artisan schedule:run` every minute and a daemon for `php artisan
horizon`. Ensure both processes restart on deployment. Postmark should be tested with a real
verified sender before launch.

If production intentionally starts from the assessment dataset, set the required seed profile and
run `php artisan db:seed --force` once before the initial search build. Do not run the deterministic
seeder over an existing event catalogue.

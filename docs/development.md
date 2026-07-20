# Local development

EventRadar runs through Laravel Sail. MySQL is the authoritative application database; SQLite
is used only by the isolated fast test suite.

## First setup

Install the PHP dependencies before asking Sail to build its application image, then start the
services and run application commands inside Sail:

```bash
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The application is available at `APP_URL`. Vite hot module replacement uses `VITE_PORT`.

After seeding, the authenticated operational workspace is available at `/admin` with the local
reviewer account `reviewer@example.test` and password `password`. Public self-registration is
enabled for attendee accounts; the reviewer account is the deterministic local administrator, not
a production credential.

Build the initial public search index after seeding:

```bash
./vendor/bin/sail artisan events:search-index
```

Mailpit is available on `FORWARD_MAILPIT_DASHBOARD_PORT`. The Sail `horizon` and `scheduler`
services consume confirmation/reminder jobs and dispatch due reminder ledger entries.

The compose project exposes MySQL on `FORWARD_DB_PORT` for optional host inspection. Application
code connects over the compose network with `DB_HOST=mysql` and `DB_PORT=3306`. The MySQL startup
hook creates both `DB_DATABASE` and the isolated `DB_TEST_DATABASE`.

The local MySQL configuration uses a 2 GB InnoDB buffer pool and 1 GB redo capacity while retaining
the default durable flush settings. This avoids the 128 MB container default thrashing during the
full indexed seed. Budget roughly 3 GB of container memory when running the full profile.

The `MYSQL_*` values and startup hook apply only when the `sail-mysql` volume is initialized for the
first time. Changing database names or credentials later does not rewrite an existing MySQL volume;
update that server deliberately instead of assuming a container restart will apply the new values.

`composer setup` installs dependencies, creates the local environment/key, and builds frontend
assets. It intentionally does not migrate because the Docker-only `mysql` hostname is unavailable
until Sail is running.

## Event seed profiles

The event seeder is deterministic and defaults to the 10,000-row `dev` profile. Use `smoke` for a
fast 500-row reset. The 1,250,000-row `full` profile is intentionally refused unless it receives an
explicit acknowledgement:

```bash
./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=smoke php artisan migrate:fresh --seed'
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=full EVENT_SEED_ALLOW_FULL=true php artisan migrate:fresh --seed'
```

`EVENT_SEED` and `EVENT_SEED_REFERENCE_AT` pin generated values, including UUIDv7 identifiers and
event dates. The seeder refuses to append if `events` already contains any row; use a deliberate
MySQL reset instead. Each insert batch runs in its own transaction so the full profile does not hold
one very large transaction open.

Run the fast SQLite-backed suite on the host and the database-contract suite inside Sail:

```bash
php artisan test
./vendor/bin/sail composer test:mysql
```

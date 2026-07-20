# Local development

Event Visuals runs through Laravel Sail. MySQL is the authoritative application database; SQLite
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

The compose project exposes MySQL on `FORWARD_DB_PORT` for optional host inspection. Application
code connects over the compose network with `DB_HOST=mysql` and `DB_PORT=3306`. The MySQL startup
hook creates both `DB_DATABASE` and the isolated `DB_TEST_DATABASE`.

The `MYSQL_*` values and startup hook apply only when the `sail-mysql` volume is initialized for the
first time. Changing database names or credentials later does not rewrite an existing MySQL volume;
update that server deliberately instead of assuming a container restart will apply the new values.

`composer setup` installs dependencies, creates the local environment/key, and builds frontend
assets. It intentionally does not migrate because the Docker-only `mysql` hostname is unavailable
until Sail is running.

## Legacy-data safety

The ignored `database/database.sqlite` file and existing Meilisearch volume belong to the archived
implementation. Until the MySQL replacement and search rebuild are explicitly approved for cleanup:

- do not point normal application commands back at SQLite;
- do not run `migrate:fresh` or `db:wipe` with a SQLite connection;
- do not run `./vendor/bin/sail down -v`;
- do not manually delete the SQLite file or Docker volumes.

Normal `./vendor/bin/sail down` is safe because it leaves named volumes intact.

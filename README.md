<p align="center">
  <img src="public/images/brand/eventradar-mark.png" alt="EventRadar mark" width="96" height="96">
</p>

<h1 align="center">EventRadar</h1>

<p align="center">
  Discover global events in a card view or on an interactive map.
</p>

<p align="center">
  <a href="https://eventradar.schmalbach.dev">Live demo</a>
  &nbsp;&middot;&nbsp;
  <a href="CODING_TEST.md">Original assessment brief</a>
</p>

EventRadar is an event discovery application built with Laravel, Inertia, Vue, and Tailwind CSS.
It uses the supplied catalogue of 1.25 million events and adds everything needed for a complete
public experience: search, filters, maps, local image galleries, clear event times and locations,
user accounts, attendance lists, confirmation emails, and event reminders.

<p align="center">
  <img src="docs/screenshots/discover-desktop.webp" alt="EventRadar Discover view" width="820">
</p>

<p align="center">
  <img src="docs/screenshots/map-desktop.webp" alt="EventRadar Near and Soon map view" width="820">
</p>

## What you can do

- Browse events as cards or explore them on a map with a list ordered by time.
- Search by text and filter by category, location, or date. Filters apply automatically.
- Open an event to see its image gallery, local date and time, venue, location, and attendee list.
- Click Show on map when you need the full address and a close-up map.
- Create an account and mark yourself as Interested or Going.
- Review or cancel your choices from the My Events page.
- Receive a confirmation email, followed by reminders three days and 24 hours before the event.
- Log in as an administrator to create, view, edit, and remove events, manage images, and inspect
  attendee lists.

The interface uses Tailwind CSS. Animations are limited to useful feedback such as the event modal,
loading indicators, dropdowns, and small hover effects. Reduced-motion settings are respected.

## How the app works

MySQL holds the complete and trusted copy of every event, user, attendance choice, and email status.
Meilisearch makes public text search and filtering fast. It returns the matching event IDs, and
Laravel then loads the current event information from MySQL. This means the search service can be
rebuilt without losing or changing the real event data.

The admin event list reads directly from MySQL, so an administrator can page through the entire
catalogue. Redis and Laravel Horizon handle email and search index updates in the background.
Mapbox provides the public maps and address lookup.

## Key decisions

### One local setup with Laravel Sail

Laravel Sail starts PHP, MySQL, Redis, Meilisearch, Horizon, the once-per-minute task runner,
Mailpit, and Vite with Docker. This gives every developer the same setup without installing each
service separately.

### MySQL instead of SQLite

SQLite can handle many applications, but MySQL is a better fit here. The application has 1.25
million events, multiple web requests, background jobs, and scheduled email work accessing the
database at the same time. MySQL also gives the admin area reliable filtering and pagination over
the complete catalogue.

### Meilisearch for fast public search

Searching and combining several filters across 1.25 million events is not a good job for normal
database queries on every keystroke. Meilisearch provides fast full-text search, typo tolerance,
location filtering, and map searches.

Meilisearch is only used to discover matching events. The unfiltered public feed and the complete
admin list still come from MySQL, so they are not limited by the maximum number of search results.

### Separate public, account, and admin areas

The public pages are designed for discovering events. The My Events page gives normal users a
simple place to manage their choices. The admin area is a practical workspace for managing the
event catalogue. After login, administrators and normal users are sent to the area intended for
them.

### Clear dates and times for global events

Each event has a named timezone such as `Europe/Berlin`. Its start and end are saved in UTC, which
is a common global reference time. The application converts them back to the event's own timezone
for display and date filtering. This also keeps reminder times correct when events are in different
countries or daylight saving time changes.

### Local image galleries

Every seeded event has two images. The assessment allows placeholder images to be reused, so the
1.25 million events share 16 carefully chosen image pairs instead of copying millions of identical
files. All 32 files are included in this repository and served by the application. There are no
external image links.

Administrators can upload between 2 and 8 images for a managed event. Uploads are checked, stripped
of unnecessary metadata, resized, converted to WebP, and saved in local storage. Images can be
added, removed, and reordered.

### Useful locations without a million Mapbox requests

The supplied events originally contain coordinates but not a complete address. During seeding,
EventRadar matches each event with a known place from a small data file included in the repository.
That file contains the city, country, coordinates, and timezone. Cards can therefore show a useful
location immediately without contacting Mapbox for every event.

When an administrator creates or edits an event, they can start typing an address and choose a
Mapbox suggestion. EventRadar saves the selected address and coordinates. The timezone is chosen
from a normal dropdown.

Opening an event does not automatically look up its street address. On public event pages, Mapbox's
address service is contacted only when someone explicitly clicks Show on map and the event does not
already have a full stored address. The returned address is cached for 90 days. This keeps the
feature useful without spending Mapbox credits on every page view.

### Attendance and reliable email reminders

A verified account lets a person choose Interested or Going, see that choice later, change it, or
cancel it. The database stores a separate record for the confirmation email, three-day reminder,
and 24-hour reminder.

Redis and Horizon send those emails in the background. If an attendance is cancelled or an event
is rescheduled, old reminders are cancelled and the correct new reminder times are created. Each
delivery is tracked in the database so retries and overlapping scheduled runs do not create a
second completed record.

### Only send the browser what it needs

Each page receives only the fields it needs. Internal source data, event-owner details, attendee
email addresses, and service credentials are not included in public page data. Small requests, such
as loading attendance status or an address only after the user asks for it, use Inertia's built-in
request helper.

## Starting point

The original starter application is preserved by the `starter` Git tag. Before adding new
features, I fixed several problems in the supplied code:

- A misspelled filter handler prevented one filter from working, and the date field did not change
  the database query.
- Some data was loaded directly by browser code, bypassing the normal Inertia flow, and the event
  list used automatic infinite scrolling where clear pagination was more useful.
- Complete event records were sent to the browser even when the page needed only a few fields. That
  could expose internal source data and event-owner information.
- Important event information lived inside a large JSON field in SQLite. I moved the fields the
  application actually uses into normal MySQL columns so they can be validated, filtered, and
  indexed efficiently.

## Local setup

You need Docker and Composer. The ports listed in `.env.example` must also be available.

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail artisan events:search-index
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The normal development seed creates 10,000 repeatable sample events. The local demo administrator
is `reviewer@example.test` with password `password`. This account is created only when
`EVENT_SEED_DEMO_ADMIN=true`, which is the default in `.env.example`. Production must set this to
`false` and create an administrator explicitly:

```bash
./vendor/bin/sail artisan user:make-admin person@example.com
```

If the email already belongs to a user, the command makes that user an administrator and verifies
the email address if needed. Otherwise, it asks for a name and password and creates a verified
administrator. Scripts can provide `--name` and `--password` without interactive questions.
Providing `--password` for an existing user deliberately changes that user's password.

Mailpit shows development emails in the browser. Its port is set by
`FORWARD_MAILPIT_DASHBOARD_PORT`. Sail also starts Horizon and the scheduled task service, so
confirmation and reminder emails work without opening extra terminals.

### Seed profiles

Use the `smoke` profile for a quick reset with 500 events. The `full` profile creates all
1,250,000 events. It requires an extra confirmation setting to prevent someone from starting the
large seed by accident:

```bash
./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=smoke php artisan migrate:fresh --seed'

./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=full EVENT_SEED_ALLOW_FULL=true php artisan migrate:fresh --seed'
```

Run `events:search-index` after replacing the whole catalogue. Normal changes made in the admin
area update only the event that changed. They do not rebuild the complete search index.

### Verification

```bash
./vendor/bin/sail composer ci:check
./vendor/bin/sail npm run build
./vendor/bin/sail composer test:mysql
./vendor/bin/sail composer test:meilisearch
npm run test:e2e
```

The main PHP test suite uses small temporary SQLite databases so it runs quickly and independently.
Separate integration tests exercise the real MySQL and Meilisearch services through Sail. The
browser tests use Playwright and expect the application to be running on the URL configured in
`playwright.config.ts`.

See [the development guide](docs/development.md) for more detail about seed data, databases,
background jobs, and importing the supplied data.

## Environment

Copy `.env.example` to `.env` and adjust these groups for production:

- `DB_*` connects Laravel to MySQL.
- `REDIS_*` and `QUEUE_CONNECTION=redis` connect background jobs to Redis. Give Horizon a
  production-specific prefix if the Redis server is shared.
- `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, and `MEILISEARCH_EVENT_INDEX` connect the public
  search.
- `VITE_MAPBOX_ACCESS_TOKEN` is the public browser token used for maps. Restrict it to the site's
  domain in Mapbox.
- `MAPBOX_GEOCODING_TOKEN` is a separate private server token used to find and save addresses.
- `MAIL_MAILER=postmark`, `POSTMARK_API_KEY`, and `POSTMARK_MESSAGE_STREAM_ID` enable Postmark.
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` set the verified sender shown on emails.
- `APP_PREVENT_INDEXING=true` asks search engines not to index a temporary assessment deployment.
- `SESSION_SECURE_COOKIE=true` should be enabled when the site uses HTTPS.
- `TRUSTED_PROXIES` should contain only the real proxy addresses when the site is behind
  Cloudflare or another proxy.

Uploaded images are saved through Laravel's public storage. Run `php artisan storage:link` so the
web server can serve them. Imagick must be installed so Laravel can check images, remove metadata,
resize them, and convert them to WebP.

## Deployment

The production server needs PHP 8.3 or newer, MySQL 8, Redis, Imagick, Meilisearch, and a web server
whose document root points to Laravel's `public` directory. Keep Meilisearch private rather than
exposing it directly to the internet.

Before the first deployment, configure the production `.env`, set `APP_DEBUG=false`, and set
`EVENT_SEED_DEMO_ADMIN=false`.

A repeatable deploy script can use:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan horizon:publish
php artisan optimize
php artisan horizon:terminate
```

Run `php artisan horizon` as a supervised background process so it restarts if the process or
server stops. Call Laravel's scheduler once per minute, for example with this cron entry:

```cron
* * * * * cd /path/to/eventradar && php artisan schedule:run >> /dev/null 2>&1
```

Create the full seed and run `events:search-index` separately from normal deployments because both
are deliberate, long-running operations. Configure and verify the Postmark sender before testing
email with real addresses.

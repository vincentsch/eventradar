<p align="center">
  <img src="public/images/brand/eventradar-mark.png" alt="EventRadar mark" width="96" height="96">
</p>

<h1 align="center">EventRadar</h1>

<p align="center">
  Discover global events through an editorial card view or a map-based agenda.
</p>

<p align="center">
  <a href="https://eventradar.schmalbach.dev">Live demo</a>
  &nbsp;&middot;&nbsp;
  <a href="CODING_TEST.md">Original assessment brief</a>
</p>

EventRadar is a Laravel and Inertia application built against a realistic 1.25-million-row event
catalogue. It supports event-local timezones, local image galleries, human-readable addresses,
automatic filtering, account-backed attendance, confirmation email, and durable reminders three
days and 24 hours before an event.

<p align="center">
  <img src="docs/screenshots/discover-desktop.webp" alt="EventRadar Discover view" width="820">
</p>

<p align="center">
  <img src="docs/screenshots/map-desktop.webp" alt="EventRadar Near and Soon map view" width="820">
</p>

## What it does

- Offers two distinct public experiences: a card-led Discover view and a map with a chronological
  agenda.
- Applies text, category, location, and date filters automatically while keeping the complete
  database-backed feed browsable.
- Presents UTC instants in each event's IANA timezone and stores an indexed event-local date.
- Serves two or more local images per event, with 2 to 8 validated uploads for managed events.
- Lets verified users choose Interested or Going, manage their events, and cancel safely.
- Sends queued confirmation and reminder emails with signed cancellation links.
- Keeps operations separate in an authenticated admin dashboard with complete event CRUD, explicit
  pagination, address lookup, image management, and attendee lists.
- Uses Tailwind CSS and restrained transitions for state feedback, with reduced-motion support.

## Architecture

MySQL is the canonical data store. Meilisearch is a replaceable discovery index that returns
compact, ordered event IDs. Laravel rechecks public visibility and loads the page data for those IDs
from MySQL, so the search index never becomes the source of event content. Admin browsing uses MySQL
directly. Redis and Horizon process queued confirmation and reminder emails, while Mapbox provides
map tiles and deliberate address lookup.

## Key decisions

### Reproducible development with Sail

Laravel Sail runs PHP, MySQL, Redis, Meilisearch, Horizon, the scheduler, Mailpit, and Vite as one
documented development environment. A clean checkout does not need host-installed service
dependencies.

### MySQL as the source of truth

MySQL owns relational event data, indexed admin pagination, users, attendance, and the email
delivery ledger. It is a more predictable operational fit than SQLite for concurrent web, queue,
and scheduled workloads over the supplied catalogue size.

### Meilisearch as bounded discovery

Meilisearch provides typo-tolerant relevance, facets, and geographic discovery. Its configured hit
window is intentional: public search is discovery, while the default public feed and complete admin
catalogue remain MySQL-backed and fully pageable.

### Separate public, account, and admin experiences

The visual public application is designed for discovery. `/my-events` is a small attendee
workspace, and `/admin` is a deliberately practical operational interface. Administrators and
normal users are redirected to the appropriate experience after authentication.

### Explicit global time handling

Each event stores UTC start and end instants alongside an IANA timezone and the event-local calendar
date. This makes display, date filtering, rescheduling, and reminder horizons unambiguous.

### Local images at catalogue scale

Seeded events reuse sixteen curated local two-image sets rather than creating millions of files.
Admin uploads are decoded, validated, stripped of metadata, resized, and saved as local WebP
variants. The database stores ordered image records, not external URLs.

### Useful addresses without a million API calls

Seeded events use a checked-in gazetteer containing a human-readable place, coordinates, and
timezone. Managed events use deliberate permanent Mapbox geocoding and store the selected result.
No page view triggers reverse geocoding for the seed catalogue.

### Account-backed attendance and durable reminders

An account gives attendees a complete way to see, change, and cancel their choice. A revision-bound
delivery ledger records confirmation and reminder work, while Redis and Horizon process it away
from web requests. Cancellation or rescheduling invalidates stale pending work, and normal job
retries do not create a second completed ledger delivery.

### Narrow Inertia data contracts

Controllers select explicit columns and build page-specific data transfer objects. Raw source
payloads, owner details, attendee email addresses, and service credentials are never shared as
general frontend props. Small lazy lookups use Inertia's HTTP helper rather than an unrelated API
layer.

## Starting point

The supplied starter is preserved by the `starter` tag. Before extending it, I corrected several
important baseline problems:

- a misspelled filter handler and date input that did not affect the query;
- direct browser fetching and automatic infinite scrolling where intentional Inertia navigation was
  clearer;
- broad event serialization that could expose raw payload and owner data;
- a payload-driven SQLite catalogue that lacked the normalized, indexed fields needed by the final
  application.

## AI-assisted engineering workflow

I treat AI agents as an engineering team and myself as the lead developer. They help across the
work with investigation, feedback, suggestions, implementation, testing, and review, but I retain
control of the product direction, architecture, trade-offs, and final code. Implementation requests
are deliberately specific about the intended outcome, constraints, and exclusions, and I inspect
the resulting code and behavior before accepting it. This is agentic engineering, not vibe coding.

## Local setup

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

The default `dev` profile creates 10,000 deterministic events. The local-only demo administrator is
`reviewer@example.test` with password `password`. This account exists only when
`EVENT_SEED_DEMO_ADMIN=true`, as configured in `.env.example`; production should leave it false and
create an administrator explicitly:

```bash
./vendor/bin/sail artisan user:make-admin person@example.com
```

The command promotes an existing account and verifies it if necessary. If the email does not exist,
it interactively asks for a name and password and creates a verified administrator. Automated
deployments can pass `--name` and `--password` non-interactively. Supplying `--password` for an
existing account intentionally resets that account's password.

Mailpit is available on `FORWARD_MAILPIT_DASHBOARD_PORT`. The Sail `horizon` and `scheduler`
services process confirmation and reminder work without extra terminals.

### Seed profiles

Use `smoke` for a quick 500-row reset. The explicit full profile creates 1,250,000 rows and refuses
to run without a separate acknowledgement:

```bash
./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=smoke php artisan migrate:fresh --seed'

./vendor/bin/sail shell -c \
  'EVENT_SEED_PROFILE=full EVENT_SEED_ALLOW_FULL=true php artisan migrate:fresh --seed'
```

Run `events:search-index` after replacing the catalogue. Normal admin changes enqueue a one-event
reconciliation and never rebuild the complete index.

### Verification

```bash
./vendor/bin/sail composer ci:check
npm run build
./vendor/bin/sail composer test:mysql
./vendor/bin/sail composer test:meilisearch
npm run test:e2e
```

The default PHP suite uses isolated SQLite databases for fast tests. The integration suites verify
the production MySQL and Meilisearch contracts through Sail.

See [the development guide](docs/development.md) for seed, database, queue, and legacy-data details.

## Environment

Start from `.env.example`. The application-specific production settings are:

- `DB_*` for MySQL 8;
- `REDIS_*`, `QUEUE_CONNECTION=redis`, and a production Horizon prefix;
- `MEILISEARCH_HOST`, `MEILISEARCH_KEY`, and `MEILISEARCH_EVENT_INDEX`;
- `VITE_MAPBOX_ACCESS_TOKEN` for a URL-restricted browser token;
- `MAPBOX_GEOCODING_TOKEN` for separate server-side permanent geocoding;
- `MAIL_MAILER=postmark`, `POSTMARK_API_KEY`, and `POSTMARK_MESSAGE_STREAM_ID`;
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` for a verified Postmark sender;
- `APP_PREVENT_INDEXING=true` for the private assessment deployment;
- `SESSION_SECURE_COOKIE=true` after HTTPS is enabled;
- `TRUSTED_PROXIES` with only the actual proxy IPs or CIDRs when Cloudflare proxying is enabled.

Uploaded images use Laravel's `public` disk and require the `public/storage` symlink. The server
needs Imagick for upload validation, metadata stripping, resizing, and WebP encoding.

## Deployment

Deploy as a normal Laravel application with its web root set to `public`, PHP 8.3 or newer, MySQL 8,
Redis, Imagick, a Horizon process, the Laravel scheduler, and a private Meilisearch instance. Set
the production environment before the first deployment, including `APP_DEBUG=false` and
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

Keep Horizon and the scheduler supervised. Run the initial full seed and `events:search-index`
outside the normal deployment command because both are deliberate, long-running operations.
Configure Postmark with a verified sender before testing real email delivery.

<?php

namespace Database\Seeders;

use App\Domain\Events\EventType;
use App\Services\Events\DeterministicEventGenerator;
use App\Services\Events\EventImageCatalogueImporter;
use App\Services\Events\EventSeedOptions;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class EventSeeder extends Seeder
{
    public const INSERT_COLUMNS = [
        'id',
        'user_id',
        'title',
        'description',
        'organizer_name',
        'venue_name',
        'starts_at',
        'ends_at',
        'timezone',
        'starts_on_local',
        'location_key',
        'locality',
        'region',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'image_set_key',
        'status',
        'type',
        'tags',
        'minimum_price',
        'currency_code',
        'capacity',
        'payload',
        'created_at',
        'updated_at',
    ];

    public function run(
        EventImageCatalogueImporter $imageCatalogue,
        DeterministicEventGenerator $generator,
    ): void {
        $options = EventSeedOptions::fromConfig();

        if (DB::table('events')->exists()) {
            throw new RuntimeException('Refusing to append seeded events to a non-empty events table.');
        }

        $startedAt = microtime(true);
        $this->command->info(
            "Seeding {$options->rowCount} deterministic events using the [{$options->profile}] profile...",
        );

        $imageCatalogue->replace();
        $ownerIds = $this->ensureUsers($options);
        $locations = $this->locations();
        $imageSetsByType = array_fill_keys(EventType::values(), []);

        foreach (DB::table('event_image_sets')->orderBy('key')->get(['key', 'category']) as $set) {
            if (! is_string($set->category) || ! is_string($set->key) || ! isset($imageSetsByType[$set->category])) {
                throw new RuntimeException('The event image catalogue contains an invalid set.');
            }

            $imageSetsByType[$set->category][] = $set->key;
        }

        DB::connection()->disableQueryLog();
        $batchSize = self::batchSizeForPlaceholderBudget(
            DB::connection()->getDriverName() === 'sqlite'
                ? min(30_000, (int) config('events.seed_placeholder_budget'))
                : (int) config('events.seed_placeholder_budget'),
        );

        for ($offset = 0; $offset < $options->rowCount; $offset += $batchSize) {
            $limit = min($options->rowCount, $offset + $batchSize);
            $batch = [];

            for ($ordinal = $offset; $ordinal < $limit; $ordinal++) {
                $row = $generator->row($ordinal, $options, $ownerIds, $imageSetsByType, $locations);
                $batch[] = array_replace(array_fill_keys(self::INSERT_COLUMNS, null), $row);
            }

            DB::transaction(fn () => DB::table('events')->insert($batch));

            if ($limit === $options->rowCount || intdiv($limit, 100_000) > intdiv($offset, 100_000)) {
                $this->command->getOutput()->writeln("  inserted {$limit}/{$options->rowCount}");
            }
        }

        $elapsed = microtime(true) - $startedAt;
        $rate = $elapsed > 0 ? (int) round($options->rowCount / $elapsed) : $options->rowCount;
        $memory = round(memory_get_peak_usage(true) / 1_048_576, 1);

        $this->command->info(sprintf(
            'Seed complete in %.2fs (%d rows/s, %s MiB peak memory).',
            $elapsed,
            $rate,
            $memory,
        ));
    }

    public static function batchSizeForPlaceholderBudget(int $placeholderBudget): int
    {
        if ($placeholderBudget < count(self::INSERT_COLUMNS)) {
            throw new RuntimeException('The seed placeholder budget is smaller than one event row.');
        }

        return intdiv($placeholderBudget, count(self::INSERT_COLUMNS));
    }

    /** @return list<int> */
    private function ensureUsers(EventSeedOptions $options): array
    {
        $ownerCount = (int) config('events.seed_owner_count');
        $demoAdminEmail = config('events.seed_demo_admin') ? 'reviewer@example.test' : null;
        $emails = $demoAdminEmail ? [$demoAdminEmail] : [];

        for ($number = 1; $number <= $ownerCount; $number++) {
            $emails[] = sprintf('event-owner-%03d@example.test', $number);
        }

        $existingEmails = DB::table('users')->whereIn('email', $emails)->pluck('email')->all();
        $missingEmails = array_values(array_diff($emails, $existingEmails));

        if ($missingEmails !== []) {
            $timestamp = $options->referenceAt->format('Y-m-d H:i:s');
            $rows = [];

            foreach ($missingEmails as $email) {
                $isDemoAdmin = $email === $demoAdminEmail;
                $rows[] = [
                    'name' => $isDemoAdmin
                        ? 'Assessment Reviewer'
                        : 'Event Owner '.substr($email, 12, 3),
                    'email' => $email,
                    'email_verified_at' => $timestamp,
                    'password' => Hash::make($isDemoAdmin ? 'password' : Str::random(64)),
                    'is_admin' => $isDemoAdmin,
                    'remember_token' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            DB::table('users')->insert($rows);
        }

        if ($demoAdminEmail) {
            DB::table('users')
                ->where('email', $demoAdminEmail)
                ->update(['is_admin' => true]);
        }

        $ownerEmails = array_values(array_filter(
            $emails,
            fn (string $email): bool => $email !== $demoAdminEmail,
        ));
        $ownerIds = [];

        foreach (DB::table('users')
            ->whereIn('email', $ownerEmails)
            ->orderBy('email')
            ->pluck('id') as $id) {
            $ownerIds[] = (int) $id;
        }

        if (count($ownerIds) !== $ownerCount) {
            throw new RuntimeException("Expected {$ownerCount} deterministic event owners.");
        }

        return $ownerIds;
    }

    /**
     * @return list<array{key: string, locality: string, region: ?string, country: string, country_code: string, timezone: string, latitude: float, longitude: float}>
     */
    private function locations(): array
    {
        $locations = require database_path('data/gazetteer.php');

        if (! is_array($locations) || count($locations) !== 75) {
            throw new RuntimeException('The seed gazetteer must contain exactly 75 locations.');
        }

        $validated = [];

        foreach ($locations as $location) {
            if (! is_array($location)
                || ! is_string($location['key'] ?? null)
                || ! is_string($location['locality'] ?? null)
                || (! is_string($location['region'] ?? null) && ($location['region'] ?? null) !== null)
                || ! is_string($location['country'] ?? null)
                || ! is_string($location['country_code'] ?? null)
                || ! is_string($location['timezone'] ?? null)
                || ! is_float($location['latitude'] ?? null)
                || ! is_float($location['longitude'] ?? null)) {
                throw new RuntimeException('The seed gazetteer contains an invalid location.');
            }

            new DateTimeZone($location['timezone']);
            $validated[] = [
                'key' => $location['key'],
                'locality' => $location['locality'],
                'region' => $location['region'],
                'country' => $location['country'],
                'country_code' => $location['country_code'],
                'timezone' => $location['timezone'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
            ];
        }

        $keys = array_column($validated, 'key');

        if (count(array_unique($keys)) !== count($validated)) {
            throw new RuntimeException('The seed gazetteer contains duplicate location keys.');
        }

        return $validated;
    }
}

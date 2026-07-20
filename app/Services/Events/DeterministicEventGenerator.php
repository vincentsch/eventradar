<?php

namespace App\Services\Events;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use DateTimeZone;
use InvalidArgumentException;

final class DeterministicEventGenerator
{
    private const ADJECTIVES = [
        'Annual', 'Global', 'Summer', 'Winter', 'Underground', 'Open', 'International', 'Live',
        'Midnight', 'Sunset', 'Urban', 'Indie', 'Grand', 'Community', 'New',
    ];

    private const THEMES = [
        'Synthwave', 'Founders', 'Jazz', 'Technology', 'Food and Wine', 'Yoga', 'Startup',
        'Design', 'Climate', 'Gaming', 'Film', 'Books', 'Marathon', 'Comedy', 'Art',
    ];

    private const FORMATS = [
        'Festival', 'Meetup', 'Conference', 'Summit', 'Workshop', 'Expo', 'Showcase', 'Gala',
        'Jam', 'Retreat', 'Fair', 'Night', 'Tour', 'Symposium', 'Block Party',
    ];

    private const VENUE_PREFIXES = [
        'Grand', 'Riverside', 'Downtown', 'Skyline', 'Harbor', 'Old Town', 'Central', 'Sunset',
    ];

    private const VENUE_SUFFIXES = [
        'Hall', 'Arena', 'Pavilion', 'Gardens', 'Warehouse', 'Theatre', 'Rooftop', 'Stadium',
    ];

    /**
     * @param  list<int>  $ownerIds
     * @param  array<string, list<string>>  $imageSetsByType
     * @param  list<array{key: string, locality: string, region: ?string, country: string, country_code: string, timezone: string, latitude: float, longitude: float}>  $locations
     * @return array<string, int|float|string|null>
     */
    public function row(
        int $ordinal,
        EventSeedOptions $options,
        array $ownerIds,
        array $imageSetsByType,
        array $locations,
    ): array {
        if ($ordinal < 0 || $ownerIds === [] || $locations === []) {
            throw new InvalidArgumentException('Event generation requires a non-negative ordinal, owners, and locations.');
        }

        $type = $this->type($options->seed, $ordinal);
        $imageSets = $imageSetsByType[$type->value] ?? [];

        if (count($imageSets) !== 2) {
            throw new InvalidArgumentException("Event type [{$type->value}] must have exactly two image sets.");
        }

        $location = $locations[$this->integer($options->seed, $ordinal, 'location', 0, count($locations) - 1)];
        new DateTimeZone($location['timezone']);

        $startOffset = $this->integer($options->seed, $ordinal, 'start', -90 * 86_400, 365 * 86_400);
        $startsAt = $options->referenceAt->addSeconds($startOffset);
        $endsAt = $startsAt->addMinutes($this->integer($options->seed, $ordinal, 'duration', 60, 72 * 60));
        $localDate = $startsAt->setTimezone($location['timezone'])->toDateString();
        $title = $this->title($options->seed, $ordinal);
        $organizer = $this->pick(self::THEMES, $options->seed, $ordinal, 'organizer').' Collective';
        $venue = $this->pick(self::VENUE_PREFIXES, $options->seed, $ordinal, 'venue-prefix').' '
            .$this->pick(self::VENUE_SUFFIXES, $options->seed, $ordinal, 'venue-suffix');
        $status = $this->status($options->seed, $ordinal);
        $latitude = $this->coordinate($location['latitude'], $options->seed, $ordinal, 'latitude', -90, 90);
        $longitude = $this->coordinate($location['longitude'], $options->seed, $ordinal, 'longitude', -180, 180);
        $priceRoll = $this->integer($options->seed, $ordinal, 'price-presence', 1, 100);
        $minimumPrice = $priceRoll <= 10
            ? null
            : number_format($this->integer($options->seed, $ordinal, 'price-cents', 0, 25_000) / 100, 2, '.', '');
        $currencyCode = $minimumPrice === null ? null : $this->currencyFor($location['country_code']);
        $capacity = $this->integer($options->seed, $ordinal, 'capacity-presence', 1, 100) <= 10
            ? null
            : $this->integer($options->seed, $ordinal, 'capacity', 20, 50_000);
        $tags = $this->tags($type, $options->seed, $ordinal);
        $createdAt = $options->referenceAt->format('Y-m-d H:i:s');
        $description = "Join {$organizer} for {$title} at {$venue} in {$location['locality']}.";

        $payload = $this->payload([
            'source' => 'deterministic-seeder',
            'seed' => $options->seed,
            'ordinal' => $ordinal,
            'external_id' => sprintf('seed-%08d', $ordinal + 1),
            'title' => $title,
            'type' => $type->value,
            'status' => $status->value,
            'location_key' => $location['key'],
            'starts_at' => $startsAt->toIso8601ZuluString(),
        ]);

        return [
            'id' => $this->uuid7($options, $ordinal),
            'user_id' => $ownerIds[$this->integer($options->seed, $ordinal, 'owner', 0, count($ownerIds) - 1)],
            'title' => $title,
            'description' => $description,
            'organizer_name' => $organizer,
            'venue_name' => $venue,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'timezone' => $location['timezone'],
            'starts_on_local' => $localDate,
            'location_key' => $location['key'],
            'locality' => $location['locality'],
            'region' => $location['region'],
            'country' => $location['country'],
            'country_code' => $location['country_code'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'image_set_key' => $imageSets[$this->integer($options->seed, $ordinal, 'image-set', 0, 1)],
            'status' => $status->value,
            'type' => $type->value,
            'tags' => json_encode($tags, JSON_THROW_ON_ERROR),
            'minimum_price' => $minimumPrice,
            'currency_code' => $currencyCode,
            'capacity' => $capacity,
            'payload' => $payload,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function type(string $seed, int $ordinal): EventType
    {
        $roll = $this->integer($seed, $ordinal, 'type', 1, 100);

        return match (true) {
            $roll <= 20 => EventType::Concert,
            $roll <= 34 => EventType::Conference,
            $roll <= 56 => EventType::Meetup,
            $roll <= 68 => EventType::Workshop,
            $roll <= 80 => EventType::Festival,
            $roll <= 88 => EventType::Sports,
            $roll <= 96 => EventType::Networking,
            default => EventType::Exhibition,
        };
    }

    private function status(string $seed, int $ordinal): EventStatus
    {
        $roll = $this->integer($seed, $ordinal, 'status', 1, 100);

        return match (true) {
            $roll <= 12 => EventStatus::Draft,
            $roll <= 82 => EventStatus::Published,
            $roll <= 90 => EventStatus::Cancelled,
            default => EventStatus::SoldOut,
        };
    }

    private function title(string $seed, int $ordinal): string
    {
        return $this->pick(self::ADJECTIVES, $seed, $ordinal, 'title-adjective').' '
            .$this->pick(self::THEMES, $seed, $ordinal, 'title-theme').' '
            .$this->pick(self::FORMATS, $seed, $ordinal, 'title-format');
    }

    /** @return list<string> */
    private function tags(EventType $type, string $seed, int $ordinal): array
    {
        $audiences = ['all-ages', 'professionals', 'families', 'students'];

        return [
            $type->value,
            $this->pick($audiences, $seed, $ordinal, 'audience'),
            $this->integer($seed, $ordinal, 'format', 0, 1) === 0 ? 'in-person' : 'featured',
        ];
    }

    /** @param array<string, int|string> $values */
    private function payload(array $values): string
    {
        $targetBytes = (int) config('events.seed_payload_bytes', 1_500);
        $values['notes'] = '';
        $encoded = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $missing = max(0, $targetBytes - strlen($encoded));
        $values['notes'] = substr(str_repeat('seeded event provenance ', (int) ceil($missing / 24)), 0, $missing);

        return json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function coordinate(float $anchor, string $seed, int $ordinal, string $field, int $minimum, int $maximum): string
    {
        $jitter = $this->integer($seed, $ordinal, $field, -800, 800) / 10_000;
        $coordinate = min($maximum, max($minimum, $anchor + $jitter));

        return number_format($coordinate, 7, '.', '');
    }

    private function currencyFor(string $countryCode): string
    {
        return match ($countryCode) {
            'US' => 'USD',
            'CA' => 'CAD',
            'MX' => 'MXN',
            'GB' => 'GBP',
            'CH' => 'CHF',
            'CZ' => 'CZK',
            'DK' => 'DKK',
            'SE' => 'SEK',
            'NO' => 'NOK',
            'PL' => 'PLN',
            'HU' => 'HUF',
            'JP' => 'JPY',
            'KR' => 'KRW',
            'SG' => 'SGD',
            'AU' => 'AUD',
            'AE' => 'AED',
            'BR' => 'BRL',
            'AR' => 'ARS',
            default => 'EUR',
        };
    }

    private function uuid7(EventSeedOptions $options, int $ordinal): string
    {
        $timestampMs = ($options->referenceAt->getTimestamp() * 1_000) + $ordinal;
        $timeHex = str_pad(dechex($timestampMs), 12, '0', STR_PAD_LEFT);
        $random = hash('sha256', "{$options->seed}|{$ordinal}|uuid");
        $hex = $timeHex.'7'.substr($random, 0, 3).'a'.substr($random, 3, 3).substr($random, 6, 12);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    /** @param list<string> $values */
    private function pick(array $values, string $seed, int $ordinal, string $field): string
    {
        return $values[$this->integer($seed, $ordinal, $field, 0, count($values) - 1)];
    }

    private function integer(string $seed, int $ordinal, string $field, int $minimum, int $maximum): int
    {
        if ($minimum > $maximum) {
            throw new InvalidArgumentException('Deterministic integer range is invalid.');
        }

        $value = (int) hexdec(substr(hash('sha256', "{$seed}|{$ordinal}|{$field}"), 0, 8));

        return $minimum + ($value % ($maximum - $minimum + 1));
    }
}

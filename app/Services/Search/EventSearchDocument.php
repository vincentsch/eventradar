<?php

namespace App\Services\Search;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use DateTimeInterface;
use LogicException;

final class EventSearchDocument
{
    public const PRIMARY_KEY = 'id';

    /** @var list<string> */
    public const SOURCE_COLUMNS = [
        'id',
        'title',
        'organizer_name',
        'venue_name',
        'starts_at',
        'ends_at',
        'starts_on_local',
        'location_key',
        'locality',
        'region',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'status',
        'type',
        'tags',
        'minimum_price',
    ];

    /** @var list<string> */
    public const DISPLAYED_ATTRIBUTES = [
        'id',
        'title',
        'organizer_name',
        'venue_name',
        'locality',
        'region',
        'country',
        'country_code',
        'location_key',
        'status',
        'type',
        'tags',
        'starts_on_local',
        'starts_on_local_number',
        'starts_at_timestamp',
        'ends_at_timestamp',
        'minimum_price',
        '_geo',
    ];

    /** @var list<string> */
    public const SEARCHABLE_ATTRIBUTES = [
        'title',
        'organizer_name',
        'venue_name',
        'locality',
        'region',
        'country',
        'tags',
    ];

    /** @var list<string> */
    public const FILTERABLE_ATTRIBUTES = [
        'status',
        'type',
        'country_code',
        'location_key',
        'starts_on_local',
        'starts_on_local_number',
        'starts_at_timestamp',
        'ends_at_timestamp',
        'minimum_price',
        '_geo',
    ];

    /** @var list<string> */
    public const SORTABLE_ATTRIBUTES = [
        'starts_at_timestamp',
        'minimum_price',
        'id',
    ];

    /** @return array<string, mixed> */
    public function build(Event $event): array
    {
        $startsAt = $event->getAttribute('starts_at');
        $endsAt = $event->getAttribute('ends_at');
        $startsOnLocal = $event->getAttribute('starts_on_local');
        $status = $event->getAttribute('status');
        $type = $event->getAttribute('type');
        $tags = $event->getAttribute('tags');
        $minimumPrice = $event->getAttribute('minimum_price');

        if (! $startsAt instanceof DateTimeInterface
            || ! $endsAt instanceof DateTimeInterface
            || ! $startsOnLocal instanceof DateTimeInterface
        ) {
            throw new LogicException("Event [{$event->getKey()}] is missing required date fields.");
        }

        if (! is_array($tags)) {
            throw new LogicException("Event [{$event->getKey()}] has invalid search tags.");
        }

        $document = [
            'id' => (string) $event->getKey(),
            'title' => (string) $event->title,
            'organizer_name' => (string) $event->organizer_name,
            'venue_name' => (string) $event->venue_name,
            'locality' => (string) $event->locality,
            'region' => $event->region === null ? null : (string) $event->region,
            'country' => (string) $event->country,
            'country_code' => (string) $event->country_code,
            'location_key' => (string) $event->location_key,
            'status' => $status instanceof EventStatus ? $status->value : (string) $status,
            'type' => $type instanceof EventType ? $type->value : (string) $type,
            'tags' => array_values(array_map('strval', $tags)),
            'starts_on_local' => $startsOnLocal->format('Y-m-d'),
            'starts_on_local_number' => (int) $startsOnLocal->format('Ymd'),
            'starts_at_timestamp' => $startsAt->getTimestamp(),
            'ends_at_timestamp' => $endsAt->getTimestamp(),
            'minimum_price' => $minimumPrice === null ? null : (float) $minimumPrice,
        ];

        if ($event->latitude !== null && $event->longitude !== null) {
            $document['_geo'] = [
                'lat' => (float) $event->latitude,
                'lng' => (float) $event->longitude,
            ];
        }

        return $document;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'displayedAttributes' => self::DISPLAYED_ATTRIBUTES,
            'searchableAttributes' => self::SEARCHABLE_ATTRIBUTES,
            'filterableAttributes' => self::FILTERABLE_ATTRIBUTES,
            'sortableAttributes' => self::SORTABLE_ATTRIBUTES,
            'pagination' => [
                'maxTotalHits' => (int) config('meilisearch.pagination_max_total_hits', 1_000),
            ],
        ];
    }
}

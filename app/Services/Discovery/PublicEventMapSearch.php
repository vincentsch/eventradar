<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class PublicEventMapSearch
{
    public const RESULT_LIMIT = 200;

    public function __construct(
        private readonly EventDiscoverySearchGateway $gateway,
        private readonly PublicEventVisibility $visibility,
        private readonly PublicEventData $data,
    ) {}

    /**
     * @param  array{q: ?string, type: ?string, location: ?string, from: ?string, to: ?string, north: ?float, south: ?float, east: ?float, west: ?float}  $criteria
     * @return array{events: list<array<string, mixed>>, provider_count: int, processing_time_ms: int}
     */
    public function search(array $criteria, CarbonImmutable $instant): array
    {
        $provider = $this->gateway->searchIds(
            $criteria['q'] ?? '',
            $this->filters($criteria, $instant),
            ['starts_at_timestamp:asc', 'id:asc'],
            1,
            self::RESULT_LIMIT,
        );

        return [
            'events' => $this->hydrate($provider->ids, $instant),
            'provider_count' => $provider->total,
            'processing_time_ms' => $provider->processingTimeMs,
        ];
    }

    /**
     * @param  array{q: ?string, type: ?string, location: ?string, from: ?string, to: ?string, north: ?float, south: ?float, east: ?float, west: ?float}  $criteria
     * @return list<string|list<string>>
     */
    private function filters(array $criteria, CarbonImmutable $instant): array
    {
        $filters = [
            'status IN '.$this->quoted(EventStatus::publicValues()),
            'ends_at_timestamp > '.$instant->getTimestamp(),
        ];

        foreach (['type', 'location'] as $key) {
            if ($criteria[$key] !== null) {
                $attribute = $key === 'location' ? 'location_key' : $key;
                $filters[] = $attribute.' = '.$this->quoted($criteria[$key]);
            }
        }

        if ($criteria['from'] !== null) {
            $filters[] = 'starts_on_local_number >= '.str_replace('-', '', $criteria['from']);
        }
        if ($criteria['to'] !== null) {
            $filters[] = 'starts_on_local_number <= '.str_replace('-', '', $criteria['to']);
        }

        if ($criteria['north'] !== null) {
            $north = (float) $criteria['north'];
            $south = (float) $criteria['south'];
            $west = (float) $criteria['west'];
            $east = (float) $criteria['east'];

            if ($west <= $east) {
                $filters[] = $this->box($north, $east, $south, $west);
            } else {
                $filters[] = [
                    $this->box($north, 180.0, $south, $west),
                    $this->box($north, $east, $south, -180.0),
                ];
            }
        }

        return $filters;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private function hydrate(array $ids, CarbonImmutable $instant): array
    {
        if ($ids === []) {
            return [];
        }

        $records = Event::query()
            ->select(PublicEventData::SOURCE_COLUMNS)
            ->with([
                'media',
                'imageSet.images' => fn ($query) => $query
                    ->select(['id', 'image_set_key', 'role', 'path', 'alt'])
                    ->orderBy('role'),
            ])
            ->whereIn('id', $ids);
        $this->visibility->apply($records, $instant);
        $byId = $records->get()->keyBy(fn (Event $event): string => (string) $event->id);

        return array_values(array_filter(array_map(
            fn (string $id): ?array => ($event = $byId->get($id)) instanceof Event
                ? $this->mapData($event)
                : null,
            $ids,
        )));
    }

    /** @return array<string, mixed> */
    private function mapData(Event $event): array
    {
        $data = $this->data->build($event);
        $data['description'] = Str::limit((string) $data['description'], 480, '...');

        return $data;
    }

    private function box(float $north, float $east, float $south, float $west): string
    {
        return sprintf('_geoBoundingBox([%.7F, %.7F], [%.7F, %.7F])', $north, $east, $south, $west);
    }

    /** @param string|list<string> $value */
    private function quoted(string|array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

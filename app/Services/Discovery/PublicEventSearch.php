<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final class PublicEventSearch
{
    public const PAGE_SIZE = 18;

    public function __construct(
        private readonly EventDiscoverySearchGateway $gateway,
        private readonly PublicEventVisibility $visibility,
        private readonly PublicEventData $data,
    ) {}

    /**
     * @return array{
     *   paginator: LengthAwarePaginator<int, array<string, mixed>>,
     *   provider_count: int,
     *   hydrated_count: int,
     *   processing_time_ms: int
     * }
     */
    public function page(PublicEventQuery $query, CarbonImmutable $instant, string $path): array
    {
        $provider = $this->gateway->searchIds(
            $query->search ?? '',
            $this->filters($query, $instant),
            ['starts_at_timestamp:asc', 'id:asc'],
            $query->page,
            self::PAGE_SIZE,
        );
        $items = $this->hydrate($provider->ids, $instant);
        $paginator = new LengthAwarePaginator(
            $items,
            $provider->total,
            self::PAGE_SIZE,
            $provider->page,
            [
                'path' => $path,
                'pageName' => 'page',
                'query' => array_filter($query->canonical(), fn ($value): bool => $value !== null),
            ],
        );

        return [
            'paginator' => $paginator,
            'provider_count' => $provider->total,
            'hydrated_count' => count($items),
            'processing_time_ms' => $provider->processingTimeMs,
        ];
    }

    /** @return list<string> */
    private function filters(PublicEventQuery $query, CarbonImmutable $instant): array
    {
        $filters = [
            'status IN '.$this->quoted(EventStatus::publicValues()),
            'ends_at_timestamp > '.$instant->getTimestamp(),
        ];

        if (! $query->includeOngoing) {
            $filters[] = 'starts_at_timestamp >= '.$instant->getTimestamp();
        }

        if ($query->types !== []) {
            $filters[] = 'type IN '.$this->quoted($query->types);
        }

        if ($query->locations !== []) {
            $filters[] = 'location_key IN '.$this->quoted($query->locations);
        }

        if ($query->from !== null) {
            $filters[] = 'starts_on_local_number >= '.str_replace('-', '', $query->from);
        }

        if ($query->to !== null) {
            $filters[] = 'starts_on_local_number <= '.str_replace('-', '', $query->to);
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
        $byId = $records->get()->keyBy(fn (Event $event): string => (string) $event->getKey());

        return array_values(array_filter(array_map(
            fn (string $id): ?array => ($event = $byId->get($id)) instanceof Event
                ? $this->data->build($event)
                : null,
            $ids,
        )));
    }

    /** @param string|list<string> $value */
    private function quoted(string|array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

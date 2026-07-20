<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventType;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PublicEventFilterOptions
{
    private const CACHE_KEY = 'public-event-location-options-v1';

    public function __construct(private readonly PublicEventVisibility $visibility) {}

    /** @return array{types: list<array{value: string, label: string}>, locations: list<array{value: string, label: string}>} */
    public function all(CarbonImmutable $instant): array
    {
        return [
            'types' => array_map(
                fn (EventType $type): array => ['value' => $type->value, 'label' => ucfirst($type->value)],
                EventType::cases(),
            ),
            'locations' => $this->locations($instant),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public function locations(CarbonImmutable $instant): array
    {
        $locations = Cache::remember(self::CACHE_KEY, now()->addHours(6), function () use ($instant): array {
            $query = Event::query();
            if (DB::connection()->getDriverName() === 'mysql') {
                $query->getQuery()->forceIndex('events_public_location_options_index');
            }
            $this->visibility->apply($query, $instant, useGeneratedStatus: true);

            return $query
                ->select(['location_key', 'locality', 'region', 'country'])
                ->groupBy(['location_key', 'locality', 'region', 'country'])
                ->orderBy('locality')
                ->orderBy('country')
                ->get()
                ->map(fn (Event $event): array => [
                    'value' => (string) $event->location_key,
                    'label' => $this->locationLabel($event),
                ])
                ->values()
                ->all();
        });

        return array_values($locations);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function locationLabel(Event $event): string
    {
        $region = trim((string) $event->region);
        $parts = [(string) $event->locality];

        if ($region !== '' && mb_strtolower($region) !== mb_strtolower((string) $event->locality)) {
            $parts[] = $region;
        }

        $parts[] = (string) $event->country;

        return implode(', ', $parts);
    }
}

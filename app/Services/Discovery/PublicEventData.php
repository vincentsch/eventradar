<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Events\ImageRole;
use App\Models\Event;
use App\Models\EventMedia;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use LogicException;

final class PublicEventData
{
    /** @var list<string> */
    public const SOURCE_COLUMNS = [
        'id',
        'title',
        'description',
        'venue_name',
        'formatted_address',
        'address_line_1',
        'starts_at',
        'ends_at',
        'timezone',
        'starts_on_local',
        'locality',
        'region',
        'postal_code',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'image_set_key',
        'status',
        'type',
    ];

    /** @return array<string, mixed> */
    public function build(Event $event): array
    {
        $startsAt = $event->getAttribute('starts_at');
        $status = $event->getAttribute('status');
        $type = $event->getAttribute('type');

        if (! $startsAt instanceof DateTimeInterface) {
            throw new LogicException("Event [{$event->getKey()}] is missing its start instant.");
        }

        $localStart = CarbonImmutable::instance($startsAt)->setTimezone((string) $event->timezone);
        $images = $this->images($event);
        $cover = $images[0];
        $detail = $images[1] ?? $images[0];

        return [
            'id' => (string) $event->getKey(),
            'status' => $status instanceof EventStatus ? $status->value : (string) $status,
            'href' => route('events.show', ['event' => $event->getKey()], false),
            'title' => (string) $event->title,
            'description' => (string) $event->description,
            'category' => $type instanceof EventType ? $type->value : (string) $type,
            'startsAt' => CarbonImmutable::instance($startsAt)->utc()->toIso8601ZuluString(),
            'localDate' => $localStart->format('Y-m-d'),
            'dateLabel' => $localStart->format('j M'),
            'timeLabel' => $localStart->format('H:i'),
            'timezoneLabel' => $localStart->format('T'),
            'timezone' => (string) $event->timezone,
            'venue' => (string) $event->venue_name,
            'locationLabel' => $this->locationLabel($event),
            'latitude' => $event->latitude === null ? null : (float) $event->latitude,
            'longitude' => $event->longitude === null ? null : (float) $event->longitude,
            'image' => $cover,
            'detailImage' => $detail,
        ];
    }

    /** @return list<array{src: string, alt: string}> */
    private function images(Event $event): array
    {
        if ($event->media->isNotEmpty()) {
            return array_values($event->media->map(fn (EventMedia $image): array => [
                'src' => $image->position === 0 ? $image->cardUrl() : $image->url(),
                'alt' => $image->alt,
            ])->all());
        }

        $images = [];
        foreach ([ImageRole::Cover, ImageRole::Detail] as $role) {
            $image = $event->imageSet?->images->first(
                function ($image) use ($role): bool {
                    $imageRole = $image->getAttribute('role');

                    return $imageRole instanceof ImageRole
                        ? $imageRole === $role
                        : (string) $imageRole === $role->value;
                },
            );

            if ($image === null) {
                throw new LogicException("Event [{$event->getKey()}] is missing its {$role->value} image.");
            }

            $images[] = ['src' => (string) $image->path, 'alt' => (string) $image->alt];
        }

        return $images;
    }

    private function locationLabel(Event $event): string
    {
        if (trim((string) $event->formatted_address) !== '') {
            return (string) $event->formatted_address;
        }

        $parts = [];

        foreach ([$event->locality, $event->region, $event->country] as $part) {
            $part = trim((string) $part);
            if ($part !== '' && ! in_array(mb_strtolower($part), array_map('mb_strtolower', $parts), true)) {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }
}

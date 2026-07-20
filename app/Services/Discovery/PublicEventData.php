<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Events\ImageRole;
use App\Models\Event;
use App\Models\EventImage;
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
        'starts_at',
        'ends_at',
        'timezone',
        'starts_on_local',
        'locality',
        'region',
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
        $cover = $this->image($event, ImageRole::Cover);
        $detail = $this->image($event, ImageRole::Detail);

        return [
            'id' => (string) $event->getKey(),
            'status' => $status instanceof EventStatus ? $status->value : (string) $status,
            'href' => route('events.show', ['event' => $event->getKey()], false),
            'title' => (string) $event->title,
            'description' => (string) $event->description,
            'category' => $type instanceof EventType ? $type->value : (string) $type,
            'startsAt' => CarbonImmutable::instance($startsAt)->utc()->toIso8601ZuluString(),
            'dateLabel' => $localStart->format('j M'),
            'timeLabel' => $localStart->format('H:i'),
            'timezoneLabel' => $localStart->format('T'),
            'timezone' => (string) $event->timezone,
            'venue' => (string) $event->venue_name,
            'locationLabel' => $this->locationLabel($event),
            'latitude' => $event->latitude === null ? null : (float) $event->latitude,
            'longitude' => $event->longitude === null ? null : (float) $event->longitude,
            'image' => $this->imageData($cover),
            'detailImage' => $this->imageData($detail),
        ];
    }

    /** @return array{src: string, alt: string} */
    private function imageData(EventImage $image): array
    {
        return [
            'src' => (string) $image->path,
            'alt' => (string) $image->alt,
        ];
    }

    private function image(Event $event, ImageRole $role): EventImage
    {
        $image = $event->imageSet?->images->first(
            function (EventImage $image) use ($role): bool {
                $imageRole = $image->getAttribute('role');

                return $imageRole instanceof ImageRole
                    ? $imageRole === $role
                    : (string) $imageRole === $role->value;
            },
        );

        if (! $image instanceof EventImage) {
            throw new LogicException("Event [{$event->getKey()}] is missing its {$role->value} image.");
        }

        return $image;
    }

    private function locationLabel(Event $event): string
    {
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

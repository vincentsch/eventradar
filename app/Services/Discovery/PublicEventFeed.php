<?php

namespace App\Services\Discovery;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\Cursor;

final class PublicEventFeed
{
    public const PAGE_SIZE = 18;

    public function __construct(
        private readonly PublicEventVisibility $visibility,
        private readonly PublicEventData $data,
    ) {}

    public function page(CarbonImmutable $instant, ?string $encodedCursor): PublicEventFeedPage
    {
        $query = Event::query()
            ->select(PublicEventData::SOURCE_COLUMNS)
            ->with([
                'media',
                'imageSet.images' => fn ($query) => $query
                    ->select(['id', 'image_set_key', 'role', 'path', 'alt'])
                    ->orderBy('role'),
            ]);

        $this->visibility->apply($query, $instant, useCursorAccessPath: true);

        $paginator = $query
            ->orderBy('starts_at')
            ->orderBy('id')
            ->cursorPaginate(
                self::PAGE_SIZE,
                ['*'],
                'cursor',
                Cursor::fromEncoded($encodedCursor),
            );

        $previousCursor = $paginator->previousCursor()?->encode();
        $nextCursor = $paginator->nextCursor()?->encode();
        $events = array_values($paginator->getCollection()
            ->map(fn (Event $event): array => $this->data->build($event))
            ->all());

        return new PublicEventFeedPage(
            events: $events,
            previousCursor: $previousCursor,
            nextCursor: $nextCursor,
            currentCursor: $encodedCursor ?? 1,
        );
    }

    public function count(CarbonImmutable $instant): int
    {
        $query = Event::query();
        $this->visibility->apply($query, $instant, useCursorAccessPath: true);

        return $query->count();
    }
}

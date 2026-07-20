<?php

use App\Services\Discovery\MeilisearchEventDiscoverySearchGateway;
use App\Services\Search\EventSearchDocument;
use App\Services\Search\EventSearchGateway;
use Illuminate\Support\Str;
use Meilisearch\Client;

it('retrieves ordered ids through the live public discovery filters', function () {
    $writeGateway = app(EventSearchGateway::class);
    $index = 'test_discovery_'.Str::lower(Str::random(12));
    config()->set('meilisearch.event_index', $index);

    try {
        requireSucceededDiscoveryTask($writeGateway, $writeGateway->createIndex($index, EventSearchDocument::PRIMARY_KEY));
        requireSucceededDiscoveryTask($writeGateway, $writeGateway->updateSettings($index, (new EventSearchDocument)->settings()));
        requireSucceededDiscoveryTask($writeGateway, $writeGateway->addDocumentsJson(
            $index,
            json_encode([
                discoveryDocument('event-b', 'Berlin Design Forum', 'published', 'exhibition', 'de-berlin', 20260802, 200, 500),
                discoveryDocument('event-a', 'Berlin Design Night', 'sold_out', 'exhibition', 'de-berlin', 20260801, 100, 500),
                discoveryDocument('ended', 'Berlin Design Archive', 'published', 'exhibition', 'de-berlin', 20260801, 90, 150),
                discoveryDocument('private', 'Berlin Design Draft', 'draft', 'exhibition', 'de-berlin', 20260801, 80, 500),
                discoveryDocument('workshop', 'Berlin Design Workshop', 'published', 'workshop', 'de-berlin', 20260801, 70, 500),
            ], JSON_THROW_ON_ERROR),
            EventSearchDocument::PRIMARY_KEY,
        ));

        $gateway = new MeilisearchEventDiscoverySearchGateway(app(Client::class));
        $result = $gateway->searchIds('Berlin Design', [
            'status IN ["published", "sold_out"]',
            'ends_at_timestamp > 200',
            'type = "exhibition"',
            'location_key = "de-berlin"',
            'starts_on_local_number >= 20260801',
            'starts_on_local_number <= 20260802',
        ], ['starts_at_timestamp:asc', 'id:asc'], 1, 18);

        expect($result->ids)->toBe(['event-a', 'event-b'])
            ->and($result->total)->toBe(2)
            ->and($result->page)->toBe(1);
    } finally {
        if ($writeGateway->indexExists($index)) {
            requireSucceededDiscoveryTask($writeGateway, $writeGateway->deleteIndex($index));
        }
    }
});

/** @return array<string, mixed> */
function discoveryDocument(
    string $id,
    string $title,
    string $status,
    string $type,
    string $location,
    int $localDate,
    int $startsAt,
    int $endsAt,
): array {
    return [
        'id' => $id,
        'title' => $title,
        'organizer_name' => 'Test',
        'venue_name' => 'Hall',
        'locality' => 'Berlin',
        'region' => null,
        'country' => 'Germany',
        'country_code' => 'DE',
        'location_key' => $location,
        'status' => $status,
        'type' => $type,
        'tags' => ['design'],
        'starts_on_local' => substr((string) $localDate, 0, 4).'-'.substr((string) $localDate, 4, 2).'-'.substr((string) $localDate, 6, 2),
        'starts_on_local_number' => $localDate,
        'starts_at_timestamp' => $startsAt,
        'ends_at_timestamp' => $endsAt,
        'minimum_price' => 0,
    ];
}

function requireSucceededDiscoveryTask(EventSearchGateway $gateway, int $taskUid): void
{
    expect($gateway->waitForTask($taskUid, 30_000)['status'])->toBe('succeeded');
}

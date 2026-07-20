<?php

use App\Services\Search\EventSearchDocument;
use App\Services\Search\EventSearchGateway;
use Illuminate\Support\Str;

it('supports the settings ingestion task verification geo and atomic swap contract', function () {
    $gateway = app(EventSearchGateway::class);
    $suffix = Str::lower(Str::random(12));
    $liveIndex = "test_events_live_{$suffix}";
    $buildIndex = "test_events_build_{$suffix}";
    $ownedIndexes = [$liveIndex, $buildIndex];

    try {
        requireSucceededSearchTask($gateway, $gateway->createIndex($buildIndex, EventSearchDocument::PRIMARY_KEY));
        requireSucceededSearchTask($gateway, $gateway->updateSettings($buildIndex, (new EventSearchDocument)->settings()));

        $documents = [
            [
                'id' => '01981f4c-3c00-7000-8000-000000000001',
                'title' => 'Berlin Design Night',
                'organizer_name' => 'Design Collective',
                'venue_name' => 'Central Hall',
                'locality' => 'Berlin',
                'region' => null,
                'country' => 'Germany',
                'country_code' => 'DE',
                'location_key' => 'de-berlin',
                'status' => 'published',
                'type' => 'exhibition',
                'tags' => ['design', 'featured'],
                'starts_on_local' => '2026-08-01',
                'starts_at_timestamp' => 1_775_066_400,
                'ends_at_timestamp' => 1_775_077_200,
                'minimum_price' => 19.9,
                '_geo' => ['lat' => 52.52, 'lng' => 13.405],
            ],
            [
                'id' => '01981f4c-3c00-7000-8000-000000000002',
                'title' => 'Remote Community Meetup',
                'organizer_name' => 'Community Group',
                'venue_name' => 'Online',
                'locality' => 'Online',
                'region' => null,
                'country' => 'Germany',
                'country_code' => 'DE',
                'location_key' => 'online',
                'status' => 'sold_out',
                'type' => 'meetup',
                'tags' => ['community'],
                'starts_on_local' => '2026-08-02',
                'starts_at_timestamp' => 1_775_152_800,
                'ends_at_timestamp' => 1_775_160_000,
                'minimum_price' => 0.0,
            ],
        ];
        $documentTaskUid = $gateway->addDocumentsJson(
            $buildIndex,
            json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            EventSearchDocument::PRIMARY_KEY,
        );
        requireSucceededSearchTask($gateway, $documentTaskUid);

        expect($gateway->tasks([$documentTaskUid]))->toHaveKey($documentTaskUid)
            ->and($gateway->stats($buildIndex)['numberOfDocuments'])->toBe(2)
            ->and($gateway->document(
                $buildIndex,
                '01981f4c-3c00-7000-8000-000000000001',
                ['id', 'minimum_price', '_geo'],
            ))->toMatchArray([
                'id' => '01981f4c-3c00-7000-8000-000000000001',
                'minimum_price' => 19.9,
                '_geo' => ['lat' => 52.52, 'lng' => 13.405],
            ]);

        requireSucceededSearchTask($gateway, $gateway->createIndex($liveIndex, EventSearchDocument::PRIMARY_KEY));
        requireSucceededSearchTask($gateway, $gateway->swapIndexes($liveIndex, $buildIndex));

        expect($gateway->stats($liveIndex)['numberOfDocuments'])->toBe(2)
            ->and($gateway->stats($buildIndex)['numberOfDocuments'])->toBe(0);

        requireSucceededSearchTask($gateway, $gateway->deleteDocuments(
            $liveIndex,
            ['01981f4c-3c00-7000-8000-000000000002'],
        ));
        expect($gateway->stats($liveIndex)['numberOfDocuments'])->toBe(1);
    } finally {
        foreach ($ownedIndexes as $indexName) {
            if ($gateway->indexExists($indexName)) {
                requireSucceededSearchTask($gateway, $gateway->deleteIndex($indexName));
            }
        }
    }
});

function requireSucceededSearchTask(EventSearchGateway $gateway, int $taskUid): void
{
    $task = $gateway->waitForTask($taskUid, 30_000);

    expect($task['status'])->toBe('succeeded');
}

<?php

use App\Services\Search\EventSearchDocumentBatcher;

it('flushes on the document limit without losing the crossing document', function () {
    $batcher = new EventSearchDocumentBatcher(documentLimit: 2, byteLimit: 1_000);

    expect($batcher->add(['id' => 'one']))->toBeNull()
        ->and($batcher->add(['id' => 'two']))->toBeNull();

    $full = $batcher->add(['id' => 'three']);
    $tail = $batcher->flush();

    expect($full['count'])->toBe(2)
        ->and(json_decode($full['json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
            ['id' => 'one'],
            ['id' => 'two'],
        ])
        ->and($tail['count'])->toBe(1)
        ->and(json_decode($tail['json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
            ['id' => 'three'],
        ]);
});

it('flushes before exceeding the exact encoded byte limit', function () {
    $one = ['id' => 'one', 'title' => str_repeat('x', 20)];
    $two = ['id' => 'two', 'title' => str_repeat('y', 20)];
    $oneBytes = strlen(json_encode([$one], JSON_THROW_ON_ERROR));
    $batcher = new EventSearchDocumentBatcher(documentLimit: 10, byteLimit: $oneBytes + 1);

    expect($batcher->add($one))->toBeNull();
    $full = $batcher->add($two);

    expect($full['count'])->toBe(1)
        ->and($full['bytes'])->toBe($oneBytes)
        ->and($batcher->flush()['count'])->toBe(1);
});

it('rejects one document larger than the configured payload cap', function () {
    $batcher = new EventSearchDocumentBatcher(documentLimit: 10, byteLimit: 20);

    expect(fn () => $batcher->add(['title' => str_repeat('x', 40)]))
        ->toThrow(LengthException::class, 'One event search document');
});

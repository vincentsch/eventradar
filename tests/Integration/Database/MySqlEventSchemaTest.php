<?php

use App\Models\Event;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
    app(EventImageCatalogueImporter::class)->replace();
});

it('creates the normalized MySQL columns, constraints, and access indexes', function () {
    $columns = DB::table('information_schema.columns')
        ->selectRaw('COLUMN_NAME AS column_name, DATA_TYPE AS data_type')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('table_name', 'events')
        ->pluck('data_type', 'column_name');

    expect($columns['starts_at'])->toBe('datetime')
        ->and($columns['ends_at'])->toBe('datetime')
        ->and($columns['starts_on_local'])->toBe('date')
        ->and($columns['payload'])->toBe('mediumtext')
        ->and($columns['tags'])->toBe('json');

    $indexes = DB::table('information_schema.statistics')
        ->selectRaw('INDEX_NAME AS index_name')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('table_name', 'events')
        ->pluck('index_name')
        ->unique()
        ->values()
        ->all();

    expect($indexes)->toContain(
        'events_public_feed_index',
        'events_public_local_date_index',
        'events_public_type_index',
        'events_location_index',
        'events_starts_at_index',
        'events_coordinates_index',
        'events_admin_type_index',
        'events_admin_country_index',
        'events_admin_local_date_index',
        'events_admin_title_index',
    );

    $sqlMode = (string) DB::selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;

    expect($sqlMode)->toContain('STRICT_TRANS_TABLES', 'ONLY_FULL_GROUP_BY');
});

it('round-trips far-future UTC domain instants exactly', function () {
    $event = Event::factory()->published()->create([
        'starts_at' => '2045-12-30 22:15:00',
        'ends_at' => '2045-12-31 01:45:00',
        'starts_on_local' => '2045-12-30',
    ])->fresh();

    expect($event?->starts_at->format('Y-m-d H:i:s'))->toBe('2045-12-30 22:15:00')
        ->and($event?->ends_at->format('Y-m-d H:i:s'))->toBe('2045-12-31 01:45:00');
});

it('rejects invalid normalized boundary combinations', function (array $changes) {
    $event = Event::factory()->create();

    expect(fn () => DB::table('events')->where('id', $event->id)->update($changes))
        ->toThrow(QueryException::class);
})->with([
    'end before start' => [[
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 17:00:00',
    ]],
    'unpaired coordinates' => [['longitude' => null]],
    'latitude outside range' => [['latitude' => 91]],
    'longitude outside range' => [['longitude' => -181]],
    'invalid country code' => [['country_code' => 'de']],
    'price without currency' => [['minimum_price' => 12.34, 'currency_code' => null]],
    'lowercase currency code' => [['minimum_price' => 12.34, 'currency_code' => 'eur']],
    'zero capacity' => [['capacity' => 0]],
]);

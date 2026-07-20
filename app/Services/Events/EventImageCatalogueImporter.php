<?php

namespace App\Services\Events;

use Illuminate\Support\Facades\DB;

final readonly class EventImageCatalogueImporter
{
    public function __construct(private EventImageManifest $manifest) {}

    /** @return array{sets: int, images: int} */
    public function replace(): array
    {
        $manifest = $this->manifest->read();

        return DB::transaction(function () use ($manifest): array {
            $setRows = [];
            $imageRows = [];

            foreach ($manifest['sets'] as $set) {
                $setRows[] = [
                    'key' => $set['key'],
                    'category' => $set['category'],
                ];

                foreach ($set['images'] as $image) {
                    $imageRows[] = [
                        'image_set_key' => $set['key'],
                        ...$image,
                    ];
                }
            }

            DB::table('event_image_sets')->upsert($setRows, ['key'], ['category']);
            DB::table('event_images')->delete();
            DB::table('event_images')->insert($imageRows);
            DB::table('event_image_sets')->whereNotIn('key', array_column($setRows, 'key'))->delete();

            return [
                'sets' => count($setRows),
                'images' => count($imageRows),
            ];
        });
    }
}

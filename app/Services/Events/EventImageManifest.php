<?php

namespace App\Services\Events;

use App\Domain\Events\EventType;
use App\Domain\Events\ImageRole;
use RuntimeException;

final class EventImageManifest
{
    public const EXPECTED_SET_COUNT = 16;

    public const EXPECTED_IMAGE_COUNT = 32;

    public function __construct(private readonly ?string $manifestPath = null) {}

    /**
     * @return array{version: int, sets: list<array{key: string, category: string, images: list<array{role: string, path: string, width: int, height: int, sha256: string, alt: string}>}>}
     */
    public function read(): array
    {
        $manifestPath = $this->manifestPath ?? public_path('images/events/manifest.json');
        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read event image manifest [{$manifestPath}].");
        }

        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== 1 || ! isset($manifest['sets']) || ! is_array($manifest['sets'])) {
            throw new RuntimeException('Event image manifest must contain version 1 and a sets array.');
        }

        $sets = [];
        $seenKeys = [];
        $seenPaths = [];
        $categoryCounts = array_fill_keys(EventType::values(), 0);

        foreach ($manifest['sets'] as $set) {
            if (! is_array($set)) {
                throw new RuntimeException('Every event image set must be an object.');
            }

            $key = $this->requiredString($set, 'key');
            $category = $this->requiredString($set, 'category');

            if (isset($seenKeys[$key])) {
                throw new RuntimeException("Duplicate event image set key [{$key}].");
            }

            if (! array_key_exists($category, $categoryCounts)) {
                throw new RuntimeException("Unknown event image category [{$category}].");
            }

            if (! isset($set['images']) || ! is_array($set['images'])) {
                throw new RuntimeException("Event image set [{$key}] must contain an images array.");
            }

            $images = [];
            $seenRoles = [];

            foreach ($set['images'] as $image) {
                if (! is_array($image)) {
                    throw new RuntimeException("Every image in set [{$key}] must be an object.");
                }

                $role = $this->requiredString($image, 'role');
                $path = $this->requiredString($image, 'path');
                $sha256 = $this->requiredString($image, 'sha256');
                $alt = $this->requiredString($image, 'alt');
                $width = $this->requiredPositiveInteger($image, 'width');
                $height = $this->requiredPositiveInteger($image, 'height');

                if (! in_array($role, ImageRole::values(), true) || isset($seenRoles[$role])) {
                    throw new RuntimeException("Set [{$key}] must contain one unique cover and detail role.");
                }

                if (! str_starts_with($path, '/images/events/') || str_contains($path, '..') || isset($seenPaths[$path])) {
                    throw new RuntimeException("Event image path [{$path}] is unsafe or duplicated.");
                }

                if (! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
                    throw new RuntimeException("Event image [{$path}] has an invalid SHA-256 value.");
                }

                $this->verifyFile($path, $sha256, $width, $height);

                $seenRoles[$role] = true;
                $seenPaths[$path] = true;
                $images[] = compact('role', 'path', 'width', 'height', 'sha256', 'alt');
            }

            $roles = array_keys($seenRoles);
            sort($roles);

            if ($roles !== ImageRole::values()) {
                $expected = implode(', ', ImageRole::values());
                throw new RuntimeException("Event image set [{$key}] must contain exactly {$expected}.");
            }

            $seenKeys[$key] = true;
            $categoryCounts[$category]++;
            $sets[] = compact('key', 'category', 'images');
        }

        if (count($sets) !== self::EXPECTED_SET_COUNT) {
            throw new RuntimeException('Event image manifest must contain exactly 16 sets.');
        }

        if (count($seenPaths) !== self::EXPECTED_IMAGE_COUNT) {
            throw new RuntimeException('Event image manifest must contain exactly 32 images.');
        }

        foreach ($categoryCounts as $category => $count) {
            if ($count !== 2) {
                throw new RuntimeException("Event image category [{$category}] must contain exactly two sets.");
            }
        }

        /** @var array{version: int, sets: list<array{key: string, category: string, images: list<array{role: string, path: string, width: int, height: int, sha256: string, alt: string}>}>} $validated */
        $validated = ['version' => 1, 'sets' => $sets];

        return $validated;
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Event image manifest field [{$key}] must be a non-empty string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function requiredPositiveInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_int($value) || $value <= 0) {
            throw new RuntimeException("Event image manifest field [{$key}] must be a positive integer.");
        }

        return $value;
    }

    private function verifyFile(string $path, string $sha256, int $width, int $height): void
    {
        $absolutePath = public_path(ltrim($path, '/'));

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Event image file [{$path}] does not exist.");
        }

        if (hash_file('sha256', $absolutePath) !== $sha256) {
            throw new RuntimeException("Event image file [{$path}] does not match its manifest hash.");
        }

        $dimensions = getimagesize($absolutePath);

        if ($dimensions === false || $dimensions[0] !== $width || $dimensions[1] !== $height) {
            throw new RuntimeException("Event image file [{$path}] does not match its manifest dimensions.");
        }
    }
}

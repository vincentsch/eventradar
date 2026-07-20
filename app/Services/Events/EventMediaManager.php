<?php

namespace App\Services\Events;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;
use Throwable;

/**
 * Prepares new local files before persistence and swaps only their metadata in
 * the caller's transaction. Replaced paths are returned for post-commit cleanup.
 */
final class EventMediaManager
{
    private const DISK = 'public';

    private const MAX_PIXELS = 40_000_000;

    /** @param list<UploadedFile> $files */
    public function prepare(Event $event, array $files): PreparedEventMedia
    {
        if (count($files) < 2 || count($files) > 8) {
            throw new RuntimeException('An event gallery must contain between two and eight images.');
        }

        $newPaths = [];
        $rows = [];

        try {
            foreach ($files as $position => $file) {
                $rows[] = $this->process($event, $file, $position, $newPaths);
            }
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPaths);

            throw $exception;
        }

        return new PreparedEventMedia($rows, $newPaths);
    }

    /** @return list<string> Paths that are safe to delete after the transaction commits. */
    public function replace(Event $event, PreparedEventMedia $prepared): array
    {
        $oldPaths = array_values($event->media()
            ->get(['path', 'card_path'])
            ->flatMap(fn ($media): array => [$media->path, $media->card_path])
            ->all());

        try {
            $event->media()->delete();
            $event->media()->createMany($prepared->rows);
        } catch (Throwable $exception) {
            $this->discard($prepared);

            throw $exception;
        }

        return $oldPaths;
    }

    /** @param list<string> $paths */
    public function deletePaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    public function discard(PreparedEventMedia $prepared): void
    {
        Storage::disk(self::DISK)->delete($prepared->paths);
    }

    /**
     * @param  list<string>  $newPaths
     * @return array<string, int|string>
     */
    private function process(Event $event, UploadedFile $file, int $position, array &$newPaths): array
    {
        $sourcePath = $file->getRealPath();
        if ($sourcePath === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $image = new Imagick;
        $image->pingImage($sourcePath);
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $image->clear();

        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('The uploaded image dimensions are not supported.');
        }

        $image->readImage($sourcePath);
        $image->setIteratorIndex(0);
        $this->orient($image);
        $image->setImagePage(0, 0, 0, 0);
        $image->stripImage();

        $large = clone $image;
        $this->shrink($large, 1600);
        $this->prepareWebp($large, 84);

        $card = clone $image;
        $this->shrink($card, 720);
        $this->prepareWebp($card, 80);

        $basename = Str::uuid()->toString();
        $directory = 'events/'.$event->id;
        $path = "{$directory}/{$basename}.webp";
        $cardPath = "{$directory}/{$basename}-card.webp";
        $largeBlob = $large->getImageBlob();
        $cardBlob = $card->getImageBlob();

        if (! Storage::disk(self::DISK)->put($path, $largeBlob)) {
            throw new RuntimeException('The optimized image could not be saved.');
        }
        $newPaths[] = $path;

        if (! Storage::disk(self::DISK)->put($cardPath, $cardBlob)) {
            throw new RuntimeException('The optimized image could not be saved.');
        }
        $newPaths[] = $cardPath;

        $row = [
            'disk' => self::DISK,
            'path' => $path,
            'card_path' => $cardPath,
            'position' => $position,
            'width' => $large->getImageWidth(),
            'height' => $large->getImageHeight(),
            'card_width' => $card->getImageWidth(),
            'card_height' => $card->getImageHeight(),
            'mime_type' => 'image/webp',
            'byte_size' => strlen($largeBlob),
            'sha256' => hash('sha256', $largeBlob),
            'alt' => Str::limit($event->title.' image '.($position + 1), 180, ''),
        ];

        $image->clear();
        $large->clear();
        $card->clear();

        return $row;
    }

    private function shrink(Imagick $image, int $maximum): void
    {
        if (max($image->getImageWidth(), $image->getImageHeight()) > $maximum) {
            $image->thumbnailImage($maximum, $maximum, true);
        }
    }

    private function prepareWebp(Imagick $image, int $quality): void
    {
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '5');
    }

    private function orient(Imagick $image): void
    {
        match ($image->getImageOrientation()) {
            2 => $image->flopImage(),
            3 => $image->rotateImage('none', 180),
            4 => $image->flipImage(),
            5 => $image->transposeImage(),
            6 => $image->rotateImage('none', 90),
            7 => $image->transverseImage(),
            8 => $image->rotateImage('none', -90),
            default => null,
        };

        $image->setImageOrientation(1);
    }
}

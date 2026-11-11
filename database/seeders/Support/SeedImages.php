<?php

namespace Database\Seeders\Support;

use App\Models\Upload;
use App\Support\PublicImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Puts the seed photos where uploads live: on the public disk as
 * uploads/<content hash>.<ext>, registered in `uploads`. So records store our
 * own paths, OwnUploadUrl accepts them, and uploads:prune keeps them (records
 * use them). The same photo used twice (an offer and a banner) is one upload.
 */
class SeedImages
{
    /**
     * Frontend path → stored path, for this run.
     *
     * @var array<string, string>
     */
    protected array $stored = [];

    /**
     * Files this run wrote to the disk (deleted again if the seeding fails).
     *
     * @var array<int, string>
     */
    protected array $written = [];

    /**
     * The stored path for a frontend photo path like "/assets/imgs/menu/x.webp".
     */
    public function path(string $frontendPath): string
    {
        if (isset($this->stored[$frontendPath])) {
            return $this->stored[$frontendPath];
        }

        $file = SeedData::imageFile($frontendPath);

        if (! is_file($file)) {
            throw new RuntimeException("Seed photo {$frontendPath} is missing from database/seeders/data/images: run `composer seed:export`.");
        }

        $contents = (string) file_get_contents($file);
        $size = getimagesizefromstring($contents);

        if ($size === false) {
            throw new RuntimeException("Seed photo {$frontendPath} isn't a readable image.");
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $path = PublicImage::UPLOADS_FOLDER.'/seed-'.substr(sha1($contents), 0, 16).'.'.$extension;

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $contents);
            $this->written[] = $path;
        }

        Upload::firstOrCreate(['path' => $path], [
            'width' => $size[0],
            'height' => $size[1],
            'size_bytes' => strlen($contents),
        ]);

        return $this->stored[$frontendPath] = $path;
    }

    /**
     * Removes the files this run wrote (the database rows go with the rolled-back transaction).
     */
    public function deleteWritten(): void
    {
        Storage::disk('public')->delete($this->written);
        $this->written = [];
    }
}

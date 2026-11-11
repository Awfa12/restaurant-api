<?php

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * POST /admin/uploads (API-CONTRACT §4 Images, decision 4.4).
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * A real file on disk: its type is detected from the content, as on the
 * server. UploadedFile::fake() reports a type from the file name instead,
 * so it can't test a file whose name lies.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

it('returns 401 without a token', function () {
    $response = $this->post('/api/v1/admin/uploads', ['image' => UploadedFile::fake()->image('dish.webp')]);

    $response->assertUnauthorized();
});

it('stores the image under a random name and returns its URL and size', function () {
    actingAsAdmin();

    $response = $this->post('/api/v1/admin/uploads', ['image' => UploadedFile::fake()->image('my dish.webp', 800, 600)]);

    $response->assertCreated()
        ->assertJsonPath('width', 800)
        ->assertJsonPath('height', 600);
    $upload = Upload::sole();
    expect($upload->path)->toStartWith('uploads/')->toEndWith('.webp')->not->toContain('my dish')
        ->and($response->json('url'))->toBe(Storage::disk('public')->url($upload->path))
        ->and($upload->width)->toBe(800)
        ->and($upload->height)->toBe(600);
    Storage::disk('public')->assertExists($upload->path);
});

it('accepts PNG and JPG photos', function (string $name) {
    actingAsAdmin();

    $response = $this->post('/api/v1/admin/uploads', ['image' => UploadedFile::fake()->image($name, 400, 400)]);

    $response->assertCreated();
})->with(['cutout.png', 'photo.jpg']);

it('returns 422 with an Arabic message for a file that is not an allowed image', function (UploadedFile $file, string $message) {
    actingAsAdmin();

    $response = $this->post('/api/v1/admin/uploads', ['image' => $file]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.image', [$message]);
    $this->assertDatabaseCount('uploads', 0);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'an SVG (it can carry scripts)' => fn () => [
        realUpload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'الصورة لازم تكون WebP أو PNG أو JPG',
    ],
    'an SVG renamed to .png (checked by content)' => fn () => [
        realUpload('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'الصورة لازم تكون WebP أو PNG أو JPG',
    ],
    'text renamed to .webp (checked by content)' => fn () => [
        realUpload('photo.webp', 'not an image at all'),
        'الصورة لازم تكون WebP أو PNG أو JPG',
    ],
    'larger than 5 MB' => fn () => [
        UploadedFile::fake()->image('big.webp', 800, 800)->size(5 * 1024 + 1),
        'الصورة كبيرة كتير (أكتر من 5MB)، اختار صورة أصغر',
    ],
    'wider than 4000 px' => fn () => [
        UploadedFile::fake()->image('wide.png', 4001, 10),
        'الصورة كبيرة كتير (أكتر من 4000 بكسل)، اختار صورة أصغر',
    ],
]);

it('returns 422 when no image is sent', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/v1/admin/uploads', []);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.image', ['اختار صورة']);
});

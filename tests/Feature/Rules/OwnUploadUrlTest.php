<?php

use App\Models\Upload;
use App\Rules\OwnUploadUrl;
use App\Support\PublicImage;
use Illuminate\Support\Facades\Validator;

/*
 * Image fields accept only the URL of one of our uploads (decision 4.3).
 */

/**
 * Whether `image` passes the rule.
 */
function passesOwnUploadUrl(mixed $value): bool
{
    return Validator::make(['image' => $value], ['image' => [new OwnUploadUrl]])->passes();
}

it('accepts the URL of an upload we stored', function () {
    $upload = Upload::factory()->create();

    expect(passesOwnUploadUrl(PublicImage::url($upload->path)))->toBeTrue();
});

it('refuses URLs that are not one of our uploads', function (Closure $url) {
    Upload::factory()->create(['path' => 'uploads/known.webp']);

    expect(passesOwnUploadUrl($url()))->toBeFalse();
})->with([
    'another host' => fn () => fn () => 'https://evil.example/uploads/known.webp',
    'our host, a path never uploaded' => fn () => fn () => PublicImage::url('uploads/never-uploaded.webp'),
    'our host, outside the uploads folder' => fn () => fn () => PublicImage::url('secrets/known.webp'),
    'path traversal' => fn () => fn () => PublicImage::url('uploads/../uploads/known.webp'),
    'not a string' => fn () => fn () => ['uploads/known.webp'],
]);

it('gives the Arabic message', function () {
    $validator = Validator::make(['image' => 'https://evil.example/x.webp'], ['image' => [new OwnUploadUrl]]);

    expect($validator->errors()->first('image'))->toBe('الصورة مش من الصور المرفوعة، ارفعها من جديد');
});

it('turns our URL back into the stored path', function () {
    expect(PublicImage::pathFromUrl(PublicImage::url('uploads/abc.webp')))->toBe('uploads/abc.webp');
});

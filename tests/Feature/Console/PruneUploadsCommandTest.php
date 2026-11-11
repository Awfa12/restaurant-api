<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

/*
 * php artisan uploads:prune: unused uploads older than 24 hours go, the rest stays.
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * An upload with its file on the fake disk, created $hoursAgo hours ago.
 */
function storedUpload(int $hoursAgo = 25): Upload
{
    $upload = Upload::factory()->create(['created_at' => now()->subHours($hoursAgo)]);
    Storage::disk('public')->put($upload->path, 'image bytes');

    return $upload;
}

it('deletes an upload no record uses once it is older than 24 hours', function () {
    $unused = storedUpload();

    $this->artisan('uploads:prune')->assertSuccessful();

    $this->assertModelMissing($unused);
    Storage::disk('public')->assertMissing($unused->path);
});

it('keeps an unused upload younger than 24 hours (its form may still be open)', function () {
    $recent = storedUpload(hoursAgo: 23);

    $this->artisan('uploads:prune')->assertSuccessful();

    $this->assertModelExists($recent);
    Storage::disk('public')->assertExists($recent->path);
});

it('keeps an old upload that a record uses', function (string $usedBy) {
    $used = storedUpload();
    match ($usedBy) {
        'category' => Category::factory()->create(['image_path' => $used->path]),
        'product' => Product::factory()->create(['image_path' => $used->path]),
        'offer' => Offer::factory()->create(['image_path' => $used->path]),
        'banner' => Banner::factory()->create(['image_path' => $used->path]),
        'about' => Setting::create(['key' => 'about', 'value' => ['imagePath' => $used->path]]),
        'offers_section' => Setting::create(['key' => 'offers_section', 'value' => ['menuCardImagePath' => $used->path]]),
    };

    $this->artisan('uploads:prune')->assertSuccessful();

    $this->assertModelExists($used);
    Storage::disk('public')->assertExists($used->path);
})->with(['category', 'product', 'offer', 'banner', 'about', 'offers_section']);

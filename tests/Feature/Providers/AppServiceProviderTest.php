<?php

use App\Models\Category;
use App\Models\Product;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;

/*
 * The safety switches in AppServiceProvider (docs/GOOD_PRACTICES.md).
 */

afterEach(function () {
    // The switches are static: put back what the testing environment boots with.
    DB::prohibitDestructiveCommands(false);
    Model::shouldBeStrict();
    app()->detectEnvironment(fn () => 'testing');
});

it('throws on lazy loading outside production', function () {
    Product::factory()->count(2)->create();

    $categories = Category::all();

    expect(fn () => $categories->first()->products)->toThrow(LazyLoadingViolationException::class);
});

it('refuses migrate:fresh in production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    $this->artisan('migrate:fresh')->assertFailed();
});

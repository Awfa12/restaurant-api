<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * The safety switches are listed in docs/GOOD_PRACTICES.md.
     */
    public function boot(): void
    {
        // The contract: responses are not wrapped in "data".
        JsonResource::withoutWrapping();

        // Outside production, turn silent Eloquent mistakes into errors: lazy loading
        // (N+1 queries), filling a non-fillable attribute, reading an unselected column.
        Model::shouldBeStrict(! $this->app->isProduction());

        // In production, refuse migrate:fresh / refresh / reset / rollback and db:wipe.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}

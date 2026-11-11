<?php

namespace App\Providers;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Support\FrontendKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Requests per minute per client on the public routes (decision 5.5). The
     * frontend caches GET /menu, so its server needs far fewer; this only stops
     * a direct caller from hammering the database.
     */
    public const PUBLIC_REQUESTS_PER_MINUTE = 300;

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

        $this->configureRateLimiting();
    }

    /**
     * throttle:public, per client IP (the browser's when the frontend vouches
     * for it). A 429 carries the contract's Arabic text and Retry-After.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(self::PUBLIC_REQUESTS_PER_MINUTE)
            ->by(FrontendKey::clientIp($request))
            ->response(fn (Request $request, array $headers) => response()->json(
                ['message' => LoginRequest::TOO_MANY_ATTEMPTS], 429, $headers,
            )));
    }
}

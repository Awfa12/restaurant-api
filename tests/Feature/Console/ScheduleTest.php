<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
 * routes/console.php: what cron runs every day on the server.
 */

it('prunes expired login tokens every day', function () {
    $events = collect(app(Schedule::class)->events());

    $prune = $events->first(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired --hours=24'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 0 * * *');
});

it('deletes unused uploads every day', function () {
    $events = collect(app(Schedule::class)->events());

    $prune = $events->first(fn (Event $event) => str_contains((string) $event->command, 'uploads:prune --hours=24'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 0 * * *');
});

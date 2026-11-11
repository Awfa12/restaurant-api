<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * php artisan admin:create (decision 2.1): the only way to create the admin
 * or change its password.
 */

it('creates the admin with a hashed password and the username as name', function () {
    $this->artisan('admin:create', ['username' => 'نورس'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsQuestion('Confirm the password', 'correct-horse-battery')
        ->assertSuccessful();

    $user = User::where('username', 'نورس')->sole();
    expect($user->name)->toBe('نورس')
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('correct-horse-battery');
});

it('normalizes the username like the login does', function () {
    $this->artisan('admin:create', ['username' => '  نورس   رشيد ', '--name' => 'نورس'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsQuestion('Confirm the password', 'correct-horse-battery')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['username' => 'نورس رشيد', 'name' => 'نورس']);
});

it('sets a new password for an existing username and logs out all its devices', function () {
    $user = User::factory()->create(['name' => 'نورس', 'username' => 'admin', 'password' => 'old-password-123']);
    $user->createToken('dashboard');

    $this->artisan('admin:create', ['username' => 'admin'])
        ->expectsQuestion('Password', 'new-password-456')
        ->expectsQuestion('Confirm the password', 'new-password-456')
        ->assertSuccessful();

    $user->refresh();
    expect(Hash::check('new-password-456', $user->password))->toBeTrue()
        ->and($user->name)->toBe('نورس');
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('refuses a password shorter than 12 characters', function () {
    $this->artisan('admin:create', ['username' => 'admin'])
        ->expectsQuestion('Password', 'short-pass1')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

it('refuses when the confirmation is different', function () {
    $this->artisan('admin:create', ['username' => 'admin'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsQuestion('Confirm the password', 'correct-horse-batterz')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

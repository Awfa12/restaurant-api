<?php

use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Support\Sleep;

/*
 * POST /auth/login, POST /auth/logout, GET /auth/me (API-CONTRACT §4 Auth).
 */

beforeEach(function () {
    // The timebox on failed logins sleeps; fake it so the suite stays fast.
    Sleep::fake();
});

/**
 * Logs in and returns the plain-text token.
 */
function loginToken(string $username = 'admin', string $password = 'correct-horse-battery'): string
{
    return test()->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password])
        ->assertOk()
        ->json('token');
}

/**
 * Fails the login $times times with a wrong password, with optional headers.
 *
 * @param  array<string, string>  $headers
 */
function failLogins(int $times, string $username = 'admin', array $headers = []): void
{
    foreach (range(1, $times) as $attempt) {
        test()->withHeaders($headers)
            ->postJson('/api/v1/auth/login', ['username' => $username, 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }
}

/**
 * Sends the next request as a fresh one: the test app keeps the resolved user
 * between requests, a real server doesn't.
 */
function forgetSignedInUser(): void
{
    app('auth')->forgetGuards();
}

describe('login', function () {
    beforeEach(function () {
        User::factory()->create(['name' => 'نورس', 'username' => 'admin', 'password' => 'correct-horse-battery']);
    });

    it('returns a token and the user name for correct credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'correct-horse-battery']);

        $response->assertOk()
            ->assertJsonPath('user', ['name' => 'نورس']);
        expect($response->json('token'))->toBeString()->toMatch('/^\d+\|\S{40,}$/');
    });

    it('gives the token an expiry 7 days ahead', function () {
        $this->freezeTime();

        loginToken();

        $this->assertDatabaseHas('personal_access_tokens', ['expires_at' => now()->addDays(7)]);
    });

    it('matches the username the way the dashboard normalizes it', function (string $stored, string $typed) {
        User::factory()->create(['username' => $stored, 'password' => 'correct-horse-battery']);

        $response = $this->postJson('/api/v1/auth/login', ['username' => $typed, 'password' => 'correct-horse-battery']);

        $response->assertOk();
    })->with([
        'extra spaces in Arabic' => ['نورس رشيد', '  نورس   رشيد '],
        'other letter case' => ['owner', 'OWNER'],
    ]);

    it('returns 422 with one Arabic message and no token for wrong credentials', function (string $username, string $password) {
        $response = $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password]);

        $response->assertUnprocessable()
            ->assertExactJson([
                'message' => 'اسم المستخدم أو كلمة السر غلط',
                'errors' => ['username' => ['اسم المستخدم أو كلمة السر غلط']],
            ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    })->with([
        'wrong password' => ['admin', 'wrong-password'],
        'unknown username' => ['nobody', 'correct-horse-battery'],
    ]);

    it('makes a failed login take the minimum time whether or not the username exists', function (string $username) {
        $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => 'wrong-password']);

        Sleep::assertSleptTimes(1);
    })->with(['admin', 'nobody']);

    it('returns 422 with Arabic messages when a field is missing', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.username', ['اكتب اسم المستخدم'])
            ->assertJsonPath('errors.password', ['اكتب كلمة السر']);
    });
});

describe('failed tries limit', function () {
    beforeEach(function () {
        User::factory()->create(['username' => 'admin', 'password' => 'correct-horse-battery']);
        User::factory()->create(['username' => 'manager', 'password' => 'correct-horse-battery']);
    });

    it('returns 429 with Retry-After after 5 failed tries, even with the right password', function () {
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS);

        $response = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'correct-horse-battery']);

        $response->assertTooManyRequests()
            ->assertJsonPath('message', 'محاولات كتير، جرّب بعد دقيقة');
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
    });

    it('lets the user in again once the minute has passed', function () {
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS);
        $this->travel(61)->seconds();

        loginToken();
    });

    it('does not block another username', function () {
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS);

        loginToken('manager');
    });

    it('starts counting again after a successful login', function () {
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS - 1);
        loginToken();
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS - 1);

        loginToken();
    });

    it('counts each browser separately when the frontend vouches for its IP', function () {
        config(['services.frontend.key' => 'shared-secret']);
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS, headers: ['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => '203.0.113.7']);

        $response = $this->withHeaders(['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => '198.51.100.20'])
            ->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'correct-horse-battery']);

        $response->assertOk();
    });

    it('ignores the client IP header without the right key', function (?string $configuredKey, array $headers) {
        config(['services.frontend.key' => $configuredKey]);
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS, headers: [...$headers, 'X-Client-IP' => '203.0.113.7']);

        $response = $this->withHeaders([...$headers, 'X-Client-IP' => '198.51.100.20'])
            ->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'correct-horse-battery']);

        $response->assertTooManyRequests();
    })->with([
        'wrong key' => ['shared-secret', ['X-Frontend-Key' => 'guessed']],
        'no key sent' => ['shared-secret', []],
        'no key configured on the server' => [null, ['X-Frontend-Key' => '']],
    ]);

    it('ignores a client IP header that is not an IP', function () {
        config(['services.frontend.key' => 'shared-secret']);
        failLogins(LoginRequest::MAX_FAILED_ATTEMPTS, headers: ['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => 'not-an-ip']);

        $response = $this->withHeaders(['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => 'also-not-an-ip'])
            ->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'correct-horse-battery']);

        $response->assertTooManyRequests();
    });
});

describe('me and logout', function () {
    beforeEach(function () {
        User::factory()->create(['name' => 'نورس', 'username' => 'admin', 'password' => 'correct-horse-battery']);
    });

    it('returns the signed-in name for a valid token', function () {
        $token = loginToken();

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertExactJson(['name' => 'نورس']);
    });

    it('returns 401 once the token is older than 7 days', function () {
        $token = loginToken();
        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    });

    it('returns 204 on logout and deletes only that device\'s token', function () {
        $laptopToken = loginToken();
        $phoneToken = loginToken();

        $this->withToken($laptopToken)->postJson('/api/v1/auth/logout')->assertNoContent();
        forgetSignedInUser();

        $this->withToken($laptopToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        forgetSignedInUser();
        $this->withToken($phoneToken)->getJson('/api/v1/auth/me')->assertOk();
    });

    it('returns 401 on logout without a token', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    });
});

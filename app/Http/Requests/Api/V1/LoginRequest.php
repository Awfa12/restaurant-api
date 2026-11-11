<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Normalizer;

/**
 * POST /auth/login (API-CONTRACT §4): checks the credentials and limits failed
 * tries to 5 per minute per username + client IP.
 */
class LoginRequest extends FormRequest
{
    /** Failed tries allowed per username + client IP before a 429. */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** How long the failed tries are counted, in seconds. */
    public const DECAY_SECONDS = 60;

    /**
     * A failed login always takes at least this long, so an unknown username
     * and a wrong password can't be told apart by the response time.
     */
    public const MIN_FAILURE_MICROSECONDS = 400_000;

    /** The frontend's text (services/auth.ts WRONG_CREDENTIALS): never says which one was wrong. */
    public const WRONG_CREDENTIALS = 'اسم المستخدم أو كلمة السر غلط';

    /** The frontend's text for a 429 (lib/api/errors.ts rate_limited). */
    public const TOO_MANY_ATTEMPTS = 'محاولات كتير، جرّب بعد دقيقة';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'اكتب اسم المستخدم',
            'password.required' => 'اكتب كلمة السر',
        ];
    }

    /**
     * The same normalization as the dashboard (services/auth.ts normalizeUsername):
     * NFC, trimmed, inner spaces collapsed, so "نورس  رشيد " matches "نورس رشيد".
     */
    public static function normalizeUsername(string $username): string
    {
        $normalized = Normalizer::normalize($username, Normalizer::FORM_C);
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $normalized === false ? $username : $normalized);

        return trim($collapsed ?? $username);
    }

    /**
     * The user for valid credentials; otherwise a 422 with one generic message,
     * or a 429 with Retry-After after too many failed tries.
     *
     * @throws ValidationException
     * @throws ThrottleRequestsException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = (new Timebox)->call(function (Timebox $timebox): ?User {
            $user = User::where('username', $this->validated('username'))->first();

            if ($user !== null && Hash::check($this->validated('password'), $user->password)) {
                $timebox->returnEarly();

                return $user;
            }

            return null;
        }, self::MIN_FAILURE_MICROSECONDS);

        if ($user === null) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages(['username' => self::WRONG_CREDENTIALS]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * The browser's IP when the Next.js server vouches for it, otherwise the
     * IP the request came from (Vercel's, for dashboard traffic).
     *
     * The frontend sends X-Client-IP with X-Frontend-Key (a shared secret,
     * FRONTEND_API_KEY). Without the right key the header is ignored, so a
     * direct caller can't pick its own IP.
     */
    public function clientIp(): string
    {
        $expectedKey = config('services.frontend.key');
        $sentKey = $this->header('X-Frontend-Key');
        $forwardedIp = $this->header('X-Client-IP');

        $isTrusted = is_string($expectedKey) && $expectedKey !== ''
            && is_string($sentKey) && hash_equals($expectedKey, $sentKey)
            && filter_var($forwardedIp, FILTER_VALIDATE_IP) !== false;

        return $isTrusted ? $forwardedIp : (string) $this->ip();
    }

    /**
     * Refuse with a 429 (and Retry-After) once the failed tries are used up.
     *
     * @throws ThrottleRequestsException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_FAILED_ATTEMPTS)) {
            return;
        }

        throw new ThrottleRequestsException(self::TOO_MANY_ATTEMPTS, null, [
            'Retry-After' => RateLimiter::availableIn($this->throttleKey()),
        ]);
    }

    /**
     * Per username + client IP. Hashed, so usernames aren't stored in the cache table.
     */
    protected function throttleKey(): string
    {
        return 'login:'.sha1(mb_strtolower($this->validated('username')).'|'.$this->clientIp());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => self::normalizeUsername($username)]);
        }
    }
}

# Laravel API-only recap (for this project)

Quick refresher on the pieces this backend uses. It's not a tutorial; it shows how each piece fits *this* setup, where Next.js is the only client.

## 1. API-only setup

- New project, no starter kit UI (no Breeze/Jetstream views). `php artisan install:api` adds Sanctum, `routes/api.php`, and the `personal_access_tokens` migration.
- Every route in `routes/api.php` gets the `/api` prefix automatically. Group them with `Route::prefix('v1')`.
- No Blade, no sessions for the API, no CSRF: the API is stateless and every admin request carries a Bearer token.
- Set `APP_LOCALE=ar` and add `lang/ar/validation.php` (`php artisan lang:publish`, then translate or use a community Arabic pack).

## 2. Sanctum personal access tokens

```php
// Login (AuthController@login)
$request->validate(['username' => 'required|string', 'password' => 'required|string']);
$user = User::where('username', $request->username)->first();
if (! $user || ! Hash::check($request->password, $user->password)) {
    throw ValidationException::withMessages(['username' => 'اسم المستخدم أو كلمة السر غلط']);
}
$token = $user->createToken('dashboard', ['*'], now()->addDays(7))->plainTextToken;
return ['token' => $token, 'user' => ['name' => $user->name]];

// Logout
$request->user()->currentAccessToken()->delete();
return response()->noContent();
```

- Protect admin routes with `->middleware('auth:sanctum')`.
- The token is stored hashed in `personal_access_tokens`; the plain one is returned only once.
- Throttle login: `->middleware('throttle:5,1')` or a named `RateLimiter::for('login', …)` keyed by username + IP. Throttled requests return `429` with a `Retry-After` header.
- Why tokens and not SPA cookie auth: the browser never talks to Laravel. Next.js stores the token in its own httpOnly cookie and sends it server to server. There are no shared cookie domains and no CSRF.

## 3. API Resources (shaping JSON)

The resource is where the camelCase and the "public vs admin" rules live.

```php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->slug,          // public id = slug
            'categoryId'      => $this->category->slug,
            'name'            => $this->name,
            'description'     => $this->description,
            'ingredients'     => $this->ingredients,   // JSON column cast to array
            'price'           => $this->price,
            'image'           => $this->image_url,
            'imageStyle'      => $this->image_style,
            'isAvailable'     => $this->is_available,
            'isBestSeller'    => $this->is_best_seller,
            'bestSellerOrder' => $this->when($this->is_best_seller, $this->best_seller_order),
            'optionGroups'    => OptionGroupResource::collection($this->whenLoaded('optionGroups')),
        ];
    }
}
```

- `JsonResource::withoutWrapping()` in `AppServiceProvider` if the frontend should get `[...]` instead of `{ data: [...] }` (agree on one with the frontend).
- `$this->when(...)` / `whenLoaded(...)` leave keys out instead of sending `null`. TypeScript optional fields (`image?`) expect the key to be absent or set.
- Eager-load (`with('optionGroups.options')`) to avoid N+1 queries.
- Optional (`?`) fields in `src/types/` → omit the key when empty; don't send `null`.

## 4. FormRequests (validation + Arabic messages)

```php
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; } // auth:sanctum already checked

    public function rules(): array
    {
        return [
            'name'                                => ['required', 'string', 'max:40'],
            'price'                               => ['required', 'integer', 'min:1'],
            'optionGroups'                        => ['nullable', 'array', 'max:1'],
            'optionGroups.*.options'              => ['required', 'array', 'min:2'],
            'optionGroups.*.options.*.priceDelta' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['price.min' => 'السعر لازم يكون رقم أكبر من صفر'];
    }
}
```

- Error keys come out as `optionGroups.0.options.1.priceDelta`, exactly what the frontend maps to fields.
- Port the rules and messages from `src/lib/dashboard/validation.ts` (the limits are in API-CONTRACT §4).
- Lengths without the `**` marks: write a custom rule (`MarkedTextLength`) that strips `**` before counting and refuses an odd number of `**`.
- Cross-record rules (category with products, WhatsApp number required) aren't field validation: return `409` from the action, e.g. `abort(409, 'ما فيك تحذف تصنيف فيه منتجات')`.

## 5. Where the logic goes

```
routes/api.php           v1: public GETs + auth + /admin (auth:sanctum)
Http/Controllers/Public  read-only, apply visibility rules
Http/Controllers/Admin   CRUD, thin: validate → call action → return resource
Http/Requests            FormRequests (rules + Arabic messages)
Http/Resources           JSON shape (camelCase, slugs as ids)
Actions/                 multi-record rules in DB transactions:
                           MoveInOrder, ToggleBestSeller, SaveSiteInfo (sync phones/branches), SaveOrdering
Models/                  Eloquent + casts (JSON columns, booleans)
database/seeders         from the frontend's mock data, same ids
```

- **Sync by id, never delete-all + recreate**: cart lines keep `optionId`, ordering keeps `phoneId`, QR codes keep branch ids.
- Wrap multi-row writes in `DB::transaction()`.

## 6. Hostinger shared hosting notes

- Check your plan has **SSH** (needed for `composer` and `artisan`). Set PHP to the newest version hPanel offers that the Laravel version supports.
- Never expose the project root. Hostinger may not let you move the document root, so the repo sits in a sibling `laravel/` folder and `public_html/` holds only a shim `index.php` plus symlinks (decision 5.1, [DEPLOYMENT.md](DEPLOYMENT.md) §1).
- Keep the `HTTP_AUTHORIZATION` rewrite in `public/.htaccess` (and `public_html/.htaccess` a symlink to it). It is what lets the Bearer token reach Laravel on Apache.
- `php artisan storage:link` for the uploaded images (if symlinks are blocked, configure the disk to write under `public/` directly).
- No Redis or queue workers on shared hosting: use the `database` or `file` drivers for cache and rate limiting. Use `sync` queues, which is fine since nothing runs in the background except the daily image cleanup via cron (`schedule:run`).
- Raise `upload_max_filesize` / `post_max_size` in hPanel's PHP settings to at least 8 MB (5 MB image + form data).
- Deploy: `bash bin/deploy.sh` over SSH (fetch + reset, `composer install --no-dev -o`, `migrate --force`, `composer cache:build`), decision 5.3. Not `php artisan optimize`: it also compiles Blade views, which an API without `resources/views` doesn't have. The full runbook is [DEPLOYMENT.md](DEPLOYMENT.md).
- `APP_DEBUG=false` in production; `.env` stays outside the public folder.

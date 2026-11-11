<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Public ids of categories, products, offers and branches (decision 4.1).
 * The owner may send a slug; otherwise a neutral one is generated
 * (`cat-3f9a1c2b`), never a transliteration of the Arabic name. A slug only
 * changes when it is sent again (API-CONTRACT §2).
 */
class Slugs
{
    /** Lowercase Latin letters and digits in groups joined by single hyphens. */
    public const FORMAT = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const MAX_LENGTH = 80;

    /**
     * Validation rules for an optional `slug` field.
     *
     * @param  array<int, string>  $tables  the tables it must be unique in (products and offers share one URL space)
     * @param  array<string, int>  $ignore  table => id of the record being edited
     * @return array<int, mixed>
     */
    public static function rules(array $tables, array $ignore = []): array
    {
        $unique = array_map(
            fn (string $table) => Rule::unique($table, 'slug')->ignore($ignore[$table] ?? null),
            $tables,
        );

        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, 'regex:'.self::FORMAT, ...$unique];
    }

    /**
     * Arabic messages for those rules (one for every unique check).
     *
     * @return array<string, string>
     */
    public static function messages(string $field = 'slug'): array
    {
        return [
            $field.'.string' => 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma',
            $field.'.regex' => 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma',
            $field.'.max' => 'الرابط لازم يكون '.self::MAX_LENGTH.' حرف أو أقل',
            $field.'.unique' => 'هالرابط مستعمل، اختار غيره',
        ];
    }

    /**
     * A neutral slug free in every given table, like the dashboard's draft ids
     * (`cat-3f9a1c2b`, `item-…`, `offer-…`, `branch-…`).
     *
     * @param  array<int, string>  $tables
     */
    public static function generate(string $prefix, array $tables): string
    {
        do {
            $slug = $prefix.'-'.Str::lower(Str::random(8));
            $taken = collect($tables)->contains(fn (string $table) => DB::table($table)->where('slug', $slug)->exists());
        } while ($taken);

        return $slug;
    }
}

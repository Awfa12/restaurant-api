<?php

namespace App\Support;

use App\Models\Setting;

/**
 * A home section's own settings (switch, texts): one `settings` row per
 * section (docs/DATABASE.md §3), read and saved whole. The seeder creates the
 * row on every server; a section never seeded counts as off.
 *
 * Each section names its key, its defaults and the fields a PATCH may change
 * (none for the hero and About, which are saved whole with replace()),
 * and can add its own handling (OffersSection's photo) in applyChanges().
 */
abstract class SectionSettings
{
    /**
     * The `settings.key` of the section.
     */
    abstract protected static function key(): string;

    /**
     * The settings of a section that was never seeded (switched off).
     *
     * @return array<string, mixed>
     */
    abstract protected static function defaults(): array;

    /**
     * The fields a PATCH copies as sent.
     *
     * @return array<int, string>
     */
    abstract protected static function fields(): array;

    /**
     * The stored settings.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return Setting::where('key', static::key())->first()?->value ?? static::defaults();
    }

    /**
     * Saves the fields that were sent (PATCH: a missing field doesn't change).
     *
     * @param  array<string, mixed>  $changes  the request's validated() data
     * @return array<string, mixed>
     */
    public static function update(array $changes): array
    {
        $settings = static::get();

        foreach (static::fields() as $field) {
            if (array_key_exists($field, $changes)) {
                $settings[$field] = $changes[$field];
            }
        }

        return static::replace(static::applyChanges($settings, $changes));
    }

    /**
     * Saves the whole row (PUT: hero, About), already in its stored shape.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function replace(array $settings): array
    {
        Setting::updateOrCreate(['key' => static::key()], ['value' => $settings]);

        return $settings;
    }

    /**
     * A section's own handling of a change (none by default).
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected static function applyChanges(array $settings, array $changes): array
    {
        return $settings;
    }
}

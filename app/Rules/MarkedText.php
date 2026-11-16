<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Editable copy with **marked** parts (frontend lib/highlight, checkSectionText):
 * the length counts the text without the markers, and an odd number of `**`
 * is refused (customers would see the stars). Reused by the reviews and About
 * texts (Phases 8, 9). Pair it with `required`/`string`; one message at a time,
 * in the frontend's order: empty, too long, stray markers.
 */
class MarkedText implements ValidationRule
{
    public const STRAY_MARKERS = 'في رمز ** زيادة، شيله';

    public function __construct(
        protected int $max,
        protected string $tooLongMessage,
        protected ?string $emptyMessage = null,
    ) {}

    /**
     * The text as customers read it: "الكرم **بالعروض**" → "الكرم بالعروض".
     */
    public static function plainText(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $plain = self::plainText($value);

        if ($this->emptyMessage !== null && trim($plain) === '') {
            $fail($this->emptyMessage);
        } elseif (mb_strlen($plain) > $this->max) {
            $fail($this->tooLongMessage);
        } elseif (substr_count($value, '**') % 2 !== 0) {
            $fail(self::STRAY_MARKERS);
        }
    }
}

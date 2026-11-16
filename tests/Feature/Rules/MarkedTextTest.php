<?php

use App\Rules\MarkedText;
use Illuminate\Support\Facades\Validator;

/*
 * Copy with **marked** parts (frontend checkSectionText): the length counts the
 * text without the markers, and an odd number of ** is refused.
 */

/**
 * The messages `title` gets with a 10-character limit.
 *
 * @return array<int, string>
 */
function markedTextErrors(string $value): array
{
    $validator = Validator::make(['title' => $value], ['title' => [new MarkedText(10, 'طويل', emptyMessage: 'فاضي')]]);

    return $validator->errors()->get('title');
}

it('accepts text whose length without the markers is within the limit', function (string $value) {
    expect(markedTextErrors($value))->toBe([]);
})->with([
    'plain' => 'الكرم',
    'exactly 10 characters once the markers are dropped' => '**الكرم** بالع',
    'several marked parts' => '**أهل** **كرم**',
]);

it('refuses text longer than the limit without the markers', function () {
    expect(markedTextErrors('الكرم **بالعروض**'))->toBe(['طويل']);
});

it('refuses an odd number of ** markers', function () {
    expect(markedTextErrors('كرم **عرض'))->toBe([MarkedText::STRAY_MARKERS]);
});

it('refuses text that is only markers and spaces', function () {
    expect(markedTextErrors('** **'))->toBe(['فاضي']);
});

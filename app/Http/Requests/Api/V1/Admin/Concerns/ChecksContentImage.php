<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Rules\OwnUploadUrl;
use Illuminate\Validation\Validator;

/**
 * A `ContentImage` field ({ src, alt }: banners, About): the photo is
 * required and must be one of our uploads. The error goes on `image`, the
 * key the contract and the dashboard use, not on `image.src`.
 */
trait ChecksContentImage
{
    /**
     * Adds the photo's error to `image`, unless the rules already put one there.
     */
    protected function checkContentImage(Validator $validator, mixed $src, string $requiredMessage): void
    {
        if ($validator->errors()->has('image')) {
            return;
        }

        if (blank($src)) {
            $validator->errors()->add('image', $requiredMessage);
        } elseif (! OwnUploadUrl::accepts($src)) {
            $validator->errors()->add('image', OwnUploadUrl::MESSAGE);
        }
    }
}

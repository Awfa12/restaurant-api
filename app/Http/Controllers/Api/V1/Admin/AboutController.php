<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAboutRequest;
use App\Http\Resources\AboutResource;
use App\Rules\MarkedText;
use App\Support\AboutContent;
use App\Support\PublicImage;

/**
 * Dashboard «من نحن» (API-CONTRACT §4 About; frontend saveAbout): one whole
 * record, read and saved together.
 */
class AboutController extends Controller
{
    /**
     * GET /admin/about → the stored AboutContent.
     */
    public function show(): AboutResource
    {
        return new AboutResource(AboutContent::get());
    }

    /**
     * PUT /admin/about: the whole section; a missing badge is cleared. The
     * photo's alt text falls back to the title without the markers (what the
     * dashboard's form sends for a new photo, decision 9.1).
     */
    public function update(UpdateAboutRequest $request): AboutResource
    {
        $input = $request->validated();
        $alt = $input['image']['alt'] ?? null;

        return new AboutResource(AboutContent::replace(array_filter([
            'eyebrow' => $input['eyebrow'],
            'title' => $input['title'],
            'paragraphs' => array_values($input['paragraphs']),
            'closing' => $input['closing'],
            'imagePath' => PublicImage::pathFromUrl($input['image']['src']),
            'imageAlt' => filled($alt) ? $alt : trim(MarkedText::plainText($input['title'])),
            'badge' => filled($input['badge'] ?? null) ? $input['badge'] : null,
        ], fn ($value) => $value !== null)));
    }
}

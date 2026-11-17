<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutResource;
use App\Support\AboutContent;

/**
 * GET /about (public, API-CONTRACT §3): what the frontend's getAboutContent()
 * shows in the home page's «من نحن».
 */
class AboutController extends Controller
{
    /**
     * The section (nothing hidden: the same as the admin GET).
     */
    public function __invoke(): AboutResource
    {
        return new AboutResource(AboutContent::get());
    }
}

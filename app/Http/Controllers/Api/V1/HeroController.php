<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeroResource;
use App\Support\HeroContent;

/**
 * GET /hero (public, API-CONTRACT §3): what the frontend's getHeroContent()
 * shows at the top of the home page.
 */
class HeroController extends Controller
{
    /**
     * The hero's texts (nothing hidden: the same as the admin GET).
     */
    public function __invoke(): HeroResource
    {
        return new HeroResource(HeroContent::get());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteInfoResource;

/**
 * GET /site-info (public, API-CONTRACT §3): what the frontend's getSiteInfo()
 * shows in the footer, the JSON-LD and the mobile menu.
 */
class SiteInfoController extends Controller
{
    /**
     * The restaurant info without the branch staff phones (they're private).
     */
    public function __invoke(): SiteInfoResource
    {
        return SiteInfoResource::forPublic();
    }
}

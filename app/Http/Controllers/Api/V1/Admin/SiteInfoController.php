<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\SaveSiteInfo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSiteInfoRequest;
use App\Http\Resources\SiteInfoResource;

/**
 * Dashboard restaurant info (API-CONTRACT §4 Site info; frontend
 * saveSiteInfo): one whole record, phones and branches saved by id.
 */
class SiteInfoController extends Controller
{
    /**
     * GET /admin/site-info → everything, branch staff phones included.
     */
    public function show(): SiteInfoResource
    {
        return SiteInfoResource::admin();
    }

    /**
     * PUT /admin/site-info: the whole info; 409 when the orders that are on
     * would be left without a WhatsApp number. Returns what was stored.
     */
    public function update(UpdateSiteInfoRequest $request, SaveSiteInfo $saveSiteInfo): SiteInfoResource
    {
        $saveSiteInfo->save($request->validated());

        return SiteInfoResource::admin();
    }
}

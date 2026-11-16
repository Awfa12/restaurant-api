<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateOffersSectionRequest;
use App\Http\Resources\OffersSectionResource;
use App\Support\OffersSection;

/**
 * PATCH /admin/offers-section (API-CONTRACT §4 Offers; frontend
 * updateOffersSettings): the section's switch, texts or menu card photo.
 */
class OffersSectionController extends Controller
{
    /**
     * Saves what was sent and returns the section's settings (OffersSettings).
     */
    public function __invoke(UpdateOffersSectionRequest $request): OffersSectionResource
    {
        return new OffersSectionResource(OffersSection::update($request->validated()));
    }
}

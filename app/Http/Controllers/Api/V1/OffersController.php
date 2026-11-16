<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\OffersContentResource;
use App\Models\Offer;
use App\Support\OffersSection;
use Illuminate\Http\JsonResponse;

/**
 * GET /offers (public, API-CONTRACT §3): what the frontend's getOffersContent()
 * returns. The home section, the menu's «العروض» card, offer pages and the
 * cart build on it.
 */
class OffersController extends Controller
{
    /**
     * The section with its active offers in order, or `null` when the section
     * is off or has no active offer (the site then hides it).
     */
    public function __invoke(OrderedList $orderedList): OffersContentResource|JsonResponse
    {
        $section = OffersSection::get();

        $offers = $section['isEnabled']
            ? $orderedList->ordered(Offer::query()->where('is_active', true))->get()
            : collect();

        if ($offers->isEmpty()) {
            // A raw `null` body: response()->json(null) would send `{}`.
            return new JsonResponse('null', 200, [], 0, true);
        }

        return new OffersContentResource(['section' => $section, 'offers' => $offers]);
    }
}

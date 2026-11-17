<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewsContentResource;
use App\Models\Review;
use App\Support\ReviewsSection;
use Illuminate\Http\JsonResponse;

/**
 * GET /reviews (public, API-CONTRACT §3): what the frontend's
 * getReviewsContent() returns for the home page's reviews section.
 */
class ReviewsController extends Controller
{
    /**
     * The section with its visible reviews in order, or `null` when the
     * section is off or has no visible review (decision 6.3).
     */
    public function __invoke(OrderedList $orderedList): ReviewsContentResource|JsonResponse
    {
        $section = ReviewsSection::get();

        if (! ReviewsSection::isShown($section)) {
            // A raw `null` body: response()->json(null) would send `{}`.
            return new JsonResponse('null', 200, [], 0, true);
        }

        return new ReviewsContentResource([
            'section' => $section,
            'reviews' => $orderedList->ordered(Review::query()->where('is_visible', true))->get(),
        ]);
    }
}

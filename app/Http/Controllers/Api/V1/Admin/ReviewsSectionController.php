<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateReviewsSectionRequest;
use App\Http\Resources\ReviewsSectionResource;
use App\Support\ReviewsSection;

/**
 * PATCH /admin/reviews-section (API-CONTRACT §4 Reviews; frontend
 * updateReviewsSettings): the section's switch, texts or default question.
 */
class ReviewsSectionController extends Controller
{
    /**
     * Saves what was sent and returns the section's settings (ReviewsSettings).
     */
    public function __invoke(UpdateReviewsSectionRequest $request): ReviewsSectionResource
    {
        return new ReviewsSectionResource(ReviewsSection::update($request->validated()));
    }
}

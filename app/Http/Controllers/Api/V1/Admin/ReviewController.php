<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MoveRequest;
use App\Http\Requests\Api\V1\Admin\PatchIsVisibleRequest;
use App\Http\Requests\Api\V1\Admin\SaveReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ReviewsContentResource;
use App\Models\Review;
use App\Support\ReviewsSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard reviews (API-CONTRACT §4 Reviews; frontend repository
 * createReview / saveReview / setReviewVisible / moveReview / deleteReview).
 * Full saves are PUT, shown/hidden PATCH (§2, decision 6.1).
 */
class ReviewController extends Controller
{
    public function __construct(protected OrderedList $orderedList) {}

    /**
     * GET /admin/reviews → the section's settings and every review (hidden
     * ones too), in order: the frontend's ReviewsContent, flat (decision 6.2).
     */
    public function index(): ReviewsContentResource
    {
        return new ReviewsContentResource([
            'section' => ReviewsSection::get(),
            'reviews' => $this->orderedList->ordered(Review::query())->get(),
        ]);
    }

    /**
     * POST /admin/reviews → 201 with the new review, last in the order.
     */
    public function store(SaveReviewRequest $request): JsonResponse
    {
        $review = new Review(['position' => $this->orderedList->nextPosition(Review::query())]);
        $this->fill($review, $request->validated());
        $review->save();

        return (new ReviewResource($review->refresh()))->response()->setStatusCode(201);
    }

    /**
     * PUT /admin/reviews/{id}: the whole review. A missing question or time is cleared.
     */
    public function update(SaveReviewRequest $request, Review $review): ReviewResource
    {
        $this->fill($review, $request->validated());
        $review->save();

        return new ReviewResource($review->refresh());
    }

    /**
     * PATCH /admin/reviews/{id} { isVisible }: shown or hidden from the list.
     */
    public function patch(PatchIsVisibleRequest $request, Review $review): ReviewResource
    {
        if ($request->has('isVisible')) {
            $review->update(['is_visible' => $request->boolean('isVisible')]);
        }

        return new ReviewResource($review);
    }

    /**
     * POST /admin/reviews/{id}/move { direction } → every review, in the new order.
     */
    public function move(MoveRequest $request, Review $review): AnonymousResourceCollection
    {
        $this->orderedList->move($review, $request->direction(), Review::query());

        return ReviewResource::collection($this->orderedList->ordered(Review::query())->get());
    }

    /**
     * DELETE /admin/reviews/{id} → 204; the rest are renumbered with no gaps.
     */
    public function destroy(Review $review): Response
    {
        DB::transaction(function () use ($review) {
            $review->delete();
            $this->orderedList->renumber(Review::query());
        });

        return response()->noContent();
    }

    /**
     * The review's columns from the request (everything but position).
     *
     * @param  array<string, mixed>  $input  SaveReviewRequest::validated()
     */
    protected function fill(Review $review, array $input): void
    {
        $review->fill([
            'customer_name' => $input['customerName'],
            'question' => filled($input['question'] ?? null) ? $input['question'] : null,
            'answer' => $input['answer'],
            'time' => filled($input['time'] ?? null) ? $input['time'] : null,
            'is_visible' => (bool) $input['isVisible'],
        ]);
    }
}

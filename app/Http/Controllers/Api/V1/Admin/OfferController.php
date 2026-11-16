<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MoveRequest;
use App\Http\Requests\Api\V1\Admin\PatchOfferRequest;
use App\Http\Requests\Api\V1\Admin\SaveOfferRequest;
use App\Http\Resources\OfferResource;
use App\Http\Resources\OffersContentResource;
use App\Models\Offer;
use App\Support\OffersSection;
use App\Support\PublicImage;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard offers (API-CONTRACT §4 Offers; frontend repository createOffer /
 * saveOffer / setOfferActive / moveOffer / deleteOffer). Full saves are PUT,
 * the on/off switch PATCH (§2).
 */
class OfferController extends Controller
{
    public function __construct(protected OrderedList $orderedList) {}

    /**
     * GET /admin/offers → the section's settings and every offer (inactive
     * ones too), in order: the frontend's OffersContent, flat.
     */
    public function index(): OffersContentResource
    {
        return new OffersContentResource([
            'section' => OffersSection::get(),
            'offers' => $this->orderedList->ordered(Offer::query())->get(),
        ]);
    }

    /**
     * POST /admin/offers → 201 with the new offer, last in the order.
     */
    public function store(SaveOfferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $offer = DB::transaction(function () use ($validated) {
            $offer = new Offer([
                'slug' => ($validated['slug'] ?? null) ?: Slugs::generate('offer', ['products', 'offers']),
                'position' => $this->orderedList->nextPosition(Offer::query()),
            ]);
            $this->fill($offer, $validated);
            $offer->save();

            return $offer;
        });

        return (new OfferResource($offer->refresh()))->response()->setStatusCode(201);
    }

    /**
     * PUT /admin/offers/{id}: the whole offer. A missing optional field
     * (imageStyle, badge) is cleared; an empty slug keeps the current one.
     */
    public function update(SaveOfferRequest $request, Offer $offer): OfferResource
    {
        $validated = $request->validated();

        if (! empty($validated['slug'])) {
            $offer->slug = $validated['slug'];
        }

        $this->fill($offer, $validated);
        $offer->save();

        return new OfferResource($offer->refresh());
    }

    /**
     * PATCH /admin/offers/{id} { isActive }: on or off from the list.
     */
    public function patch(PatchOfferRequest $request, Offer $offer): OfferResource
    {
        if ($request->has('isActive')) {
            $offer->update(['is_active' => $request->boolean('isActive')]);
        }

        return new OfferResource($offer);
    }

    /**
     * POST /admin/offers/{id}/move { direction } → every offer, in the new order.
     */
    public function move(MoveRequest $request, Offer $offer): AnonymousResourceCollection
    {
        $this->orderedList->move($offer, $request->direction(), Offer::query());

        return OfferResource::collection($this->orderedList->ordered(Offer::query())->get());
    }

    /**
     * DELETE /admin/offers/{id} → 204; the rest are renumbered with no gaps.
     */
    public function destroy(Offer $offer): Response
    {
        DB::transaction(function () use ($offer) {
            $offer->delete();
            $this->orderedList->renumber(Offer::query());
        });

        return response()->noContent();
    }

    /**
     * The offer's own columns from the request (everything but slug and position).
     *
     * @param  array<string, mixed>  $input  SaveOfferRequest::validated()
     */
    protected function fill(Offer $offer, array $input): void
    {
        $offer->fill([
            'title' => $input['title'],
            'items' => $input['items'],
            'free_item' => $input['freeItem'],
            'price' => (int) $input['price'],
            'image_path' => PublicImage::pathFromUrl($input['image']),
            'image_style' => $input['imageStyle'] ?? null,
            'badge' => $input['badge'] ?? null,
            'is_active' => (bool) $input['isActive'],
        ]);
    }
}

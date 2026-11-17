<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\OrderedList;
use App\Enums\BannerTargetKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MoveRequest;
use App\Http\Requests\Api\V1\Admin\PatchIsActiveRequest;
use App\Http\Requests\Api\V1\Admin\SaveBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Support\PublicImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard menu banner slides (API-CONTRACT §4 Banners; frontend repository
 * createBanner / saveBanner / setBannerActive / moveBanner / deleteBanner).
 * Full saves are PUT, the on/off switch PATCH (§2, decision 6.1).
 */
class BannerController extends Controller
{
    /** What BannerResource reads for the button's target. */
    protected const TARGETS = ['targetCategory', 'targetProduct'];

    public function __construct(protected OrderedList $orderedList) {}

    /**
     * GET /admin/banners → every slide (inactive ones too), in order. A deleted
     * target is sent with an empty id, so the dashboard shows "target gone".
     */
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection($this->orderedList->ordered(Banner::query())->with(self::TARGETS)->get());
    }

    /**
     * POST /admin/banners → 201 with the new slide, last in the order.
     */
    public function store(SaveBannerRequest $request): JsonResponse
    {
        $banner = new Banner(['position' => $this->orderedList->nextPosition(Banner::query())]);
        $this->fill($banner, $request->validated());
        $banner->save();

        return (new BannerResource($banner->refresh()->load(self::TARGETS)))->response()->setStatusCode(201);
    }

    /**
     * PUT /admin/banners/{id}: the whole slide. A missing eyebrow or button is cleared.
     */
    public function update(SaveBannerRequest $request, Banner $banner): BannerResource
    {
        $this->fill($banner, $request->validated());
        $banner->save();

        return new BannerResource($banner->refresh()->load(self::TARGETS));
    }

    /**
     * PATCH /admin/banners/{id} { isActive }: on or off from the list.
     */
    public function patch(PatchIsActiveRequest $request, Banner $banner): BannerResource
    {
        if ($request->has('isActive')) {
            $banner->update(['is_active' => $request->boolean('isActive')]);
        }

        return new BannerResource($banner->load(self::TARGETS));
    }

    /**
     * POST /admin/banners/{id}/move { direction } → every slide, in the new order.
     */
    public function move(MoveRequest $request, Banner $banner): AnonymousResourceCollection
    {
        $this->orderedList->move($banner, $request->direction(), Banner::query());

        return $this->index();
    }

    /**
     * DELETE /admin/banners/{id} → 204; the rest are renumbered with no gaps.
     */
    public function destroy(Banner $banner): Response
    {
        DB::transaction(function () use ($banner) {
            $banner->delete();
            $this->orderedList->renumber(Banner::query());
        });

        return response()->noContent();
    }

    /**
     * The slide's columns from the request. The photo's alt text falls back to
     * the title on one line (what the dashboard's form sends anyway).
     *
     * @param  array<string, mixed>  $input  SaveBannerRequest::validated()
     */
    protected function fill(Banner $banner, array $input): void
    {
        $action = $input['action'] ?? null;
        $kind = $action === null ? null : BannerTargetKind::from($action['target']['kind']);
        $alt = $input['image']['alt'] ?? null;

        $banner->fill([
            'eyebrow' => filled($input['eyebrow'] ?? null) ? $input['eyebrow'] : null,
            'title' => $input['title'],
            'image_path' => PublicImage::pathFromUrl($input['image']['src']),
            'image_alt' => filled($alt) ? $alt : str_replace("\n", ' ', $input['title']),
            'action_label' => $action['label'] ?? null,
            'target_kind' => $kind,
            'target_category_id' => $kind === BannerTargetKind::Category ? Category::where('slug', $action['target']['categoryId'])->value('id') : null,
            'target_product_id' => $kind === BannerTargetKind::Product ? Product::where('slug', $action['target']['productId'])->value('id') : null,
            'is_active' => (bool) $input['isActive'],
        ]);
    }
}

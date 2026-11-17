<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Support\OffersSection;
use Illuminate\Support\Collection;

/**
 * GET /banners (public, API-CONTRACT §3): the slides the frontend's
 * getMenuBanners() shows at the top of the menu.
 */
class BannersController extends Controller
{
    /**
     * Active slides in order. A button whose target isn't on the site (a
     * deleted or empty category, a deleted product, the offers hidden) is left
     * out; the slide stays. A fixed number of queries, whatever the count.
     *
     * @return Collection<int, BannerResource>
     */
    public function __invoke(OrderedList $orderedList): Collection
    {
        $offersShown = OffersSection::isShown();

        return $orderedList->ordered(Banner::query()->where('is_active', true))
            ->with(['targetCategory' => fn ($query) => $query->withCount('products'), 'targetProduct'])
            ->get()
            ->map(fn (Banner $banner) => BannerResource::forPublic($banner, $offersShown));
    }
}

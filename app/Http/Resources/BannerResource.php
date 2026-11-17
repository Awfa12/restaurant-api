<?php

namespace App\Http\Resources;

use App\Enums\BannerTargetKind;
use App\Models\Banner;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `MenuBanner` (src/types/content.ts). The database id is the id;
 * the button's target is sent as stored (decision 7.1: the frontend builds
 * the link). Needs `targetCategory` and `targetProduct` loaded.
 *
 * Two views of a target that is gone (deleted, or not on the public menu):
 * - admin (`$offersShown` null): the button stays, its id `""`, so the
 *   dashboard shows "target gone" (API-CONTRACT §4 Banners);
 * - public (`$offersShown` set): the button is left out, the slide stays.
 *
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    /**
     * Public view only: whether the offers section is on the site. Null = the
     * admin view. Not a constructor argument on purpose: collection() builds
     * items with mapInto(), which passes each item's key as the second argument.
     */
    protected ?bool $offersShown = null;

    /**
     * The slide as the public site gets it: a button that leads nowhere is left out.
     */
    public static function forPublic(Banner $banner, bool $offersShown): self
    {
        $resource = new self($banner);
        $resource->offersShown = $offersShown;

        return $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'eyebrow' => $this->when(filled($this->eyebrow), $this->eyebrow),
            'title' => $this->title,
            'image' => ['src' => PublicImage::url($this->image_path), 'alt' => $this->image_alt],
            'action' => $this->when($this->hasButton(), fn () => [
                'label' => $this->action_label,
                'target' => $this->target(),
            ]),
            'isActive' => $this->is_active,
        ];
    }

    /**
     * Whether the slide has a button to show (in the public view: one that leads somewhere).
     */
    protected function hasButton(): bool
    {
        if ($this->action_label === null || $this->target_kind === null) {
            return false;
        }

        return $this->offersShown === null || $this->targetIsReachable();
    }

    /**
     * The target on the public site: the offers section shown, a category
     * on the menu (it has products), or a product that still exists.
     */
    protected function targetIsReachable(): bool
    {
        return match ($this->target_kind) {
            BannerTargetKind::Offers => $this->offersShown,
            BannerTargetKind::Category => ($this->targetCategory?->products_count ?? 0) > 0,
            BannerTargetKind::Product => $this->targetProduct !== null,
        };
    }

    /**
     * Frontend `BannerTarget`, ids as slugs; a deleted category or product as `""`.
     *
     * @return array<string, string>
     */
    protected function target(): array
    {
        return match ($this->target_kind) {
            BannerTargetKind::Offers => ['kind' => 'offers'],
            BannerTargetKind::Category => ['kind' => 'category', 'categoryId' => $this->targetCategory->slug ?? ''],
            BannerTargetKind::Product => ['kind' => 'product', 'productId' => $this->targetProduct->slug ?? ''],
        };
    }
}

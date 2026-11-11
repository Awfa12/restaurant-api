<?php

namespace App\Enums;

/**
 * Where a menu banner button goes (frontend `BannerTarget.kind`).
 * Picked from a list in the dashboard, never a typed link.
 */
enum BannerTargetKind: string
{
    /** The offers section of the menu. */
    case Offers = 'offers';

    /** A menu category (banners.target_category_id). */
    case Category = 'category';

    /** A product page (banners.target_product_id). */
    case Product = 'product';
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderingResource;

/**
 * GET /ordering (public, API-CONTRACT §3): what the frontend's
 * getOrderingOptions() gives the checkout and the table QR pages.
 */
class OrderingController extends Controller
{
    /**
     * Only what's on, with the WhatsApp numbers worked out.
     */
    public function __invoke(): OrderingResource
    {
        return OrderingResource::forPublic();
    }
}

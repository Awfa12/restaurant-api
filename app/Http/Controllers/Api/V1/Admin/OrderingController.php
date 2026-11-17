<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\SaveOrdering;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateOrderingRequest;
use App\Http\Resources\OrderingResource;

/**
 * Dashboard ordering settings (API-CONTRACT §4 Ordering; frontend
 * saveOrdering): one whole record, checked against the saved restaurant info.
 */
class OrderingController extends Controller
{
    /**
     * GET /admin/ordering → OrderingSettings, every branch included.
     */
    public function show(): OrderingResource
    {
        return OrderingResource::admin();
    }

    /**
     * PUT /admin/ordering: the whole settings. Returns what was stored.
     */
    public function update(UpdateOrderingRequest $request, SaveOrdering $saveOrdering): OrderingResource
    {
        $saveOrdering->save($request->validated());

        return OrderingResource::admin();
    }
}

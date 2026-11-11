<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUploadRequest;
use App\Models\Upload;
use App\Support\PublicImage;
use Illuminate\Http\JsonResponse;

/**
 * Image uploads for the dashboard (API-CONTRACT §4 Images). A record then
 * stores the returned URL; uploads no record uses are deleted after 24 hours
 * (uploads:prune).
 */
class UploadController extends Controller
{
    /**
     * POST /admin/uploads → 201 { url, width, height }.
     */
    public function store(StoreUploadRequest $request): JsonResponse
    {
        $file = $request->file('image');

        // A random file name, with the extension of the detected content type.
        $path = $file->store(PublicImage::UPLOADS_FOLDER, 'public');
        [$width, $height] = getimagesize($file->getRealPath());

        Upload::create([
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'size_bytes' => $file->getSize(),
        ]);

        return response()->json([
            'url' => PublicImage::url($path),
            'width' => $width,
            'height' => $height,
        ], 201);
    }
}

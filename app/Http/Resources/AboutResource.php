<?php

namespace App\Http\Resources;

use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `AboutContent` (src/types/content.ts), from the array AboutContent
 * stores. The photo is sent as a URL; an empty badge is left out.
 *
 * @property array{eyebrow: string, title: string, paragraphs: array<int, string>, closing: string, imagePath?: string, imageAlt: string, badge?: string} $resource
 */
class AboutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imagePath = $this->resource['imagePath'] ?? null;
        $badge = $this->resource['badge'] ?? null;

        return [
            'eyebrow' => $this->resource['eyebrow'],
            'title' => $this->resource['title'],
            'paragraphs' => $this->resource['paragraphs'],
            'closing' => $this->resource['closing'],
            'image' => ['src' => filled($imagePath) ? PublicImage::url($imagePath) : '', 'alt' => $this->resource['imageAlt']],
            'badge' => $this->when(filled($badge), $badge),
        ];
    }
}

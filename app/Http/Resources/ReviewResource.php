<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `Review` (src/types/content.ts). The database id is the id; an
 * empty question is left out (the site shows the section's default one), and
 * so is an empty time.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'customerName' => $this->customer_name,
            'question' => $this->when(filled($this->question), $this->question),
            'answer' => $this->answer,
            'time' => $this->when(filled($this->time), $this->time),
            'isVisible' => $this->is_visible,
        ];
    }
}

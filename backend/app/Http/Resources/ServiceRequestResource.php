<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'urgency' => $this->urgency,
            'business_area' => $this->business_area,
            'status' => $this->status,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'customer_id' => $this->customer_id,
            'provider_id' => $this->provider_id,

            // Only present on nearby queries (selectRaw adds `distance` column)
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn() => (float) $this->distance_km
            ),

            'customer' => new UserResource($this->whenLoaded('customer')),
            'provider' => new UserResource($this->whenLoaded('provider')),
            'attachments' => $this->attachments ?? [],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

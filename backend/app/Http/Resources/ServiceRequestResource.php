<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
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
            'status' => $this->status,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            // Only present on nearby queries (selectRaw adds `distance` column)
            'distance_km' => $this->when(
                isset($this->distance),
                fn() => round((float) $this->distance, 2)
            ),

            'customer' => new UserResource($this->whenLoaded('customer')),
            'provider' => new UserResource($this->whenLoaded('provider')),
            'attachments' => $this->attachments ?? [],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

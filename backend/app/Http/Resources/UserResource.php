<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'plan' => $this->plan,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded('permissions', fn() => $this->getAllPermissions()->pluck('name')),
            'request_count' => $this->serviceRequests()->whereMonth('created_at', now()->month)->count(),
            'limit_reached' => $this->hasExceededRequestLimit(),
            'free_limit' => 3,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

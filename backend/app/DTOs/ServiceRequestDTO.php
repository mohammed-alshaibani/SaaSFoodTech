<?php

namespace App\DTOs;

class ServiceRequestDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $category = null,
        public readonly array $attachments = []
    ) {
    }

    /**
     * Create a DTO from an array (e.g., from validated request data).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            category: $data['category'] ?? null,
            attachments: $data['attachments'] ?? []
        );
    }
}

<?php

namespace App\Services\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceInterface
{
    /**
     * Get all resources.
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get paginated resources.
     */
    public function getPaginated(array $filters = []): LengthAwarePaginator;

    /**
     * Find resource by ID.
     */
    public function findById(int $id): ?Model;

    /**
     * Create new resource.
     */
    public function create(array $data): Model;

    /**
     * Update existing resource.
     */
    public function update(int $id, array $data): Model;

    /**
     * Delete resource.
     */
    public function delete(int $id): bool;

    /**
     * Validate business rules.
     */
    public function validate(array $data, ?int $id = null): array;

    /**
     * Get resource statistics.
     */
    public function getStatistics(): array;
}

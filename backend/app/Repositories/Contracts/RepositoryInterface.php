<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(): Collection;

    /**
     * Find a record by ID.
     */
    public function find(int $id): ?Model;

    /**
     * Find a record by ID or throw exception.
     */
    public function findOrFail(int $id): Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update a record.
     */
    public function update(int $id, array $data): Model;

    /**
     * Delete a record.
     */
    public function delete(int $id): bool;

    /**
     * Get paginated results.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Apply filters to query.
     */
    public function applyFilters(array $filters): self;

    /**
     * Apply sorting to query.
     */
    public function applySorting(array $sort): self;

    /**
     * Get query builder.
     */
    public function query();

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit a database transaction.
     */
    public function commit(): void;

    /**
     * Rollback a database transaction.
     */
    public function rollback(): void;
}

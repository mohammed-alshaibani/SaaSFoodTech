<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;
    protected Builder $query;
    protected array $with = [];
    protected array $filters = [];
    protected array $sort = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
    }

    /**
     * Get all records.
     */
    public function all(): Collection
    {
        return $this->applyRelationships()->query->get();
    }

    /**
     * Find a record by ID.
     */
    public function find(int $id): ?Model
    {
        return $this->applyRelationships()->query->find($id);
    }

    /**
     * Find a record by ID or throw exception.
     */
    public function findOrFail(int $id): Model
    {
        return $this->applyRelationships()->query->findOrFail($id);
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);
        return $model;
    }

    /**
     * Delete a record.
     */
    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);
        return $model->delete();
    }

    /**
     * Get paginated results.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $this->applyFilters($filters);
        
        return $this->applyRelationships()
            ->applySorting()
            ->query
            ->paginate($perPage);
    }

    /**
     * Apply filters to query.
     */
    public function applyFilters(array $filters): self
    {
        $this->filters = $filters;
        
        foreach ($filters as $key => $value) {
            if (method_exists($this, "filterBy{$key}")) {
                $this->{"filterBy{$key}"}($value);
            }
        }
        
        return $this;
    }

    /**
     * Apply sorting to query.
     */
    public function applySorting(array $sort = []): self
    {
        $this->sort = $sort;
        
        if (!empty($sort['field']) && !empty($sort['direction'])) {
            $this->query->orderBy($sort['field'], $sort['direction']);
        } else {
            $this->query->latest();
        }
        
        return $this;
    }

    /**
     * Get query builder.
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    /**
     * Commit a database transaction.
     */
    public function commit(): void
    {
        DB::commit();
    }

    /**
     * Rollback a database transaction.
     */
    public function rollback(): void
    {
        DB::rollBack();
    }

    /**
     * Set relationships to eager load.
     */
    protected function with(array $relations): self
    {
        $this->with = $relations;
        return $this;
    }

    /**
     * Apply relationships to query.
     */
    protected function applyRelationships(): self
    {
        if (!empty($this->with)) {
            $this->query->with($this->with);
        }
        
        return $this;
    }

    /**
     * Reset query builder.
     */
    protected function resetQuery(): self
    {
        $this->query = $this->model->newQuery();
        $this->with = [];
        $this->filters = [];
        $this->sort = [];
        
        return $this;
    }

    /**
     * Get count of records.
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Check if record exists.
     */
    public function exists(int $id): bool
    {
        return $this->query->where('id', $id)->exists();
    }

    /**
     * Find multiple records by IDs.
     */
    public function findMany(array $ids): Collection
    {
        return $this->applyRelationships()->query->findMany($ids);
    }

    /**
     * Create multiple records.
     */
    public function createMany(array $data): Collection
    {
        $models = collect();
        
        foreach ($data as $item) {
            $models->push($this->create($item));
        }
        
        return $models;
    }

    /**
     * Update or create record.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    /**
     * Get first record matching criteria.
     */
    public function first(): ?Model
    {
        return $this->applyRelationships()->query->first();
    }

    /**
     * Get first record or create new one.
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->model->firstOrCreate($attributes, $values);
    }

    /**
     * Chunk results for processing large datasets.
     */
    public function chunk(int $chunkSize, callable $callback): bool
    {
        return $this->query->chunk($chunkSize, $callback);
    }
}

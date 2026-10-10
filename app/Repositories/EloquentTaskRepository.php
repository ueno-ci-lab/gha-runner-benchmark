<?php

namespace App\Repositories;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Collection;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    public function list(?TaskStatus $status = null): Collection
    {
        return Task::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?Task
    {
        return Task::query()->find($id);
    }

    public function create(array $attributes): Task
    {
        return Task::query()->create($attributes);
    }

    public function update(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function countByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = Task::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return $counts;
    }
}

<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /**
     * @return Collection<int, Task>
     */
    public function list(?TaskStatus $status = null): Collection
    {
        return $this->tasks->list($status);
    }

    public function findOrFail(int $id): Task
    {
        return $this->tasks->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Task::class, [$id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Task
    {
        // 新規作成時は必ず未着手から開始する
        $attributes['status'] = TaskStatus::Todo;

        return $this->tasks->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Task
    {
        $task = $this->findOrFail($id);

        if (isset($attributes['status'])) {
            $next = $attributes['status'] instanceof TaskStatus
                ? $attributes['status']
                : TaskStatus::from($attributes['status']);

            if ($next !== $task->status && ! $task->status->canTransitionTo($next)) {
                throw new InvalidStatusTransitionException($task->status, $next);
            }

            $attributes['status'] = $next;
        }

        return $this->tasks->update($task, $attributes);
    }

    public function delete(int $id): void
    {
        $this->tasks->delete($this->findOrFail($id));
    }

    /**
     * @return array{total: int, by_status: array<string, int>, completion_rate: float, overdue: int}
     */
    public function summary(?Carbon $now = null): array
    {
        $counts = $this->tasks->countByStatus();

        $byStatus = [];
        foreach (TaskStatus::cases() as $status) {
            $byStatus[$status->value] = $counts[$status->value] ?? 0;
        }

        $total = array_sum($byStatus);
        $overdue = $this->tasks->list()
            ->filter(fn (Task $task) => $task->isOverdue($now))
            ->count();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'completion_rate' => $total === 0 ? 0.0 : round($byStatus[TaskStatus::Done->value] / $total * 100, 1),
            'overdue' => $overdue,
        ];
    }
}

<?php

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    /** @var TaskRepositoryInterface&MockInterface */
    private TaskRepositoryInterface $repository;

    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var TaskRepositoryInterface&MockInterface $repository */
        $repository = Mockery::mock(TaskRepositoryInterface::class);
        $this->repository = $repository;
        $this->service = new TaskService($this->repository);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTask(array $attributes = []): Task
    {
        return Task::factory()->make(['id' => 1, ...$attributes]);
    }

    public function test_list_passes_status_filter(): void
    {
        $tasks = collect([$this->makeTask(['status' => TaskStatus::Doing])]);
        $this->repository->shouldReceive('list')->once()->with(TaskStatus::Doing)->andReturn($tasks);

        $this->assertSame($tasks, $this->service->list(TaskStatus::Doing));
    }

    public function test_find_or_fail_returns_task(): void
    {
        $task = $this->makeTask();
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($task);

        $this->assertSame($task, $this->service->findOrFail(1));
    }

    public function test_find_or_fail_throws_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->with(99)->andReturnNull();

        $this->expectException(ModelNotFoundException::class);

        $this->service->findOrFail(99);
    }

    public function test_create_always_starts_as_todo(): void
    {
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attributes) => $attributes['status'] === TaskStatus::Todo
                && $attributes['title'] === 'new task'))
            ->andReturnUsing(fn (array $attributes) => $this->makeTask($attributes));

        $task = $this->service->create(['title' => 'new task', 'status' => 'done']);

        $this->assertSame(TaskStatus::Todo, $task->status);
    }

    /**
     * @return array<string, array{TaskStatus, string}>
     */
    public static function allowedTransitionProvider(): array
    {
        return [
            'todo -> doing' => [TaskStatus::Todo, 'doing'],
            'doing -> todo' => [TaskStatus::Doing, 'todo'],
            'doing -> done' => [TaskStatus::Doing, 'done'],
            'todo -> todo（変更なし）' => [TaskStatus::Todo, 'todo'],
            'done -> done（変更なし）' => [TaskStatus::Done, 'done'],
        ];
    }

    #[DataProvider('allowedTransitionProvider')]
    public function test_update_allows_valid_transition(TaskStatus $from, string $to): void
    {
        $task = $this->makeTask(['status' => $from]);
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($task);
        $this->repository->shouldReceive('update')
            ->once()
            ->with($task, ['status' => TaskStatus::from($to)])
            ->andReturnUsing(fn (Task $task, array $attributes) => $task->fill($attributes));

        $updated = $this->service->update(1, ['status' => $to]);

        $this->assertSame(TaskStatus::from($to), $updated->status);
    }

    /**
     * @return array<string, array{TaskStatus, string}>
     */
    public static function rejectedTransitionProvider(): array
    {
        return [
            'todo -> done' => [TaskStatus::Todo, 'done'],
            'done -> todo' => [TaskStatus::Done, 'todo'],
            'done -> doing' => [TaskStatus::Done, 'doing'],
        ];
    }

    #[DataProvider('rejectedTransitionProvider')]
    public function test_update_rejects_invalid_transition(TaskStatus $from, string $to): void
    {
        $this->repository->shouldReceive('find')->once()->andReturn($this->makeTask(['status' => $from]));
        $this->repository->shouldNotReceive('update');

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->update(1, ['status' => $to]);
    }

    public function test_update_without_status_skips_transition_check(): void
    {
        $task = $this->makeTask(['status' => TaskStatus::Done]);
        $this->repository->shouldReceive('find')->once()->andReturn($task);
        $this->repository->shouldReceive('update')->once()->with($task, ['title' => 'renamed'])->andReturn($task);

        $this->service->update(1, ['title' => 'renamed']);
    }

    public function test_update_throws_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->andReturnNull();
        $this->repository->shouldNotReceive('update');

        $this->expectException(ModelNotFoundException::class);

        $this->service->update(1, ['title' => 'x']);
    }

    public function test_delete(): void
    {
        $task = $this->makeTask();
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($task);
        $this->repository->shouldReceive('delete')->once()->with($task);

        $this->service->delete(1);
    }

    public function test_delete_throws_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->andReturnNull();
        $this->repository->shouldNotReceive('delete');

        $this->expectException(ModelNotFoundException::class);

        $this->service->delete(1);
    }

    public function test_summary(): void
    {
        $this->repository->shouldReceive('countByStatus')->once()->andReturn(['todo' => 2, 'doing' => 1, 'done' => 1]);
        $this->repository->shouldReceive('list')->once()->andReturn(collect([
            $this->makeTask(['status' => TaskStatus::Todo, 'due_date' => '2026-10-01']),
            $this->makeTask(['status' => TaskStatus::Todo, 'due_date' => '2026-10-20']),
            $this->makeTask(['status' => TaskStatus::Doing, 'due_date' => '2026-10-09']),
            $this->makeTask(['status' => TaskStatus::Done, 'due_date' => '2026-10-01']),
        ]));

        $summary = $this->service->summary(Carbon::parse('2026-10-10'));

        $this->assertSame([
            'total' => 4,
            'by_status' => ['todo' => 2, 'doing' => 1, 'done' => 1],
            'completion_rate' => 25.0,
            'overdue' => 2,
        ], $summary);
    }

    public function test_summary_when_empty(): void
    {
        $this->repository->shouldReceive('countByStatus')->once()->andReturn([]);
        $this->repository->shouldReceive('list')->once()->andReturn(collect());

        $this->assertSame([
            'total' => 0,
            'by_status' => ['todo' => 0, 'doing' => 0, 'done' => 0],
            'completion_rate' => 0.0,
            'overdue' => 0,
        ], $this->service->summary());
    }
}

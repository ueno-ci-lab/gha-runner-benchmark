<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    /** @var TaskRepositoryInterface&MockInterface */
    private TaskRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // DB には接続せず、リポジトリをモックに差し替える
        $this->repository = $this->mock(TaskRepositoryInterface::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTask(array $attributes = []): Task
    {
        return Task::factory()->make([
            'id' => 1,
            'title' => 'task',
            'description' => null,
            'status' => TaskStatus::Todo,
            'priority' => 3,
            'due_date' => null,
            ...$attributes,
        ]);
    }

    public function test_index(): void
    {
        $this->repository->shouldReceive('list')->once()->with(null)->andReturn(collect([
            $this->makeTask(['id' => 1, 'title' => 'first']),
            $this->makeTask(['id' => 2, 'title' => 'second', 'status' => TaskStatus::Doing]),
        ]));

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'first')
            ->assertJsonPath('data.1.status', 'doing')
            ->assertJsonPath('data.1.status_label', '進行中');
    }

    #[DataProvider('statusFilterProvider')]
    public function test_index_filters_by_status(string $status): void
    {
        $this->repository->shouldReceive('list')->once()->with(TaskStatus::from($status))->andReturn(collect());

        $this->getJson("/api/tasks?status={$status}")->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusFilterProvider(): array
    {
        return array_combine(
            array_column(TaskStatus::cases(), 'value'),
            array_map(fn (TaskStatus $status) => [$status->value], TaskStatus::cases()),
        );
    }

    public function test_index_rejects_unknown_status(): void
    {
        $this->repository->shouldNotReceive('list');

        $this->getJson('/api/tasks?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_show(): void
    {
        $this->repository->shouldReceive('find')->once()->with(1)
            ->andReturn($this->makeTask(['title' => 'detail', 'priority' => 5, 'due_date' => '2026-12-24']));

        $this->getJson('/api/tasks/1')
            ->assertOk()
            ->assertJsonPath('data.title', 'detail')
            ->assertJsonPath('data.priority', 5)
            ->assertJsonPath('data.due_date', '2026-12-24');
    }

    public function test_show_returns_404_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->with(999)->andReturnNull();

        $this->getJson('/api/tasks/999')->assertNotFound();
    }

    public function test_show_returns_404_for_non_numeric_id(): void
    {
        $this->repository->shouldNotReceive('find');

        $this->getJson('/api/tasks/abc')->assertNotFound();
    }

    public function test_store(): void
    {
        $this->repository->shouldReceive('create')
            ->once()
            ->with([
                'title' => 'write tests',
                'priority' => 4,
                'due_date' => '2026-11-01',
                'status' => TaskStatus::Todo,
            ])
            ->andReturnUsing(fn (array $attributes) => $this->makeTask(['id' => 10, ...$attributes]));

        $this->postJson('/api/tasks', ['title' => 'write tests', 'priority' => 4, 'due_date' => '2026-11-01'])
            ->assertCreated()
            ->assertJsonPath('data.id', 10)
            ->assertJsonPath('data.status', 'todo');
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStorePayloadProvider(): array
    {
        return [
            'タイトルなし' => [[], 'title'],
            'タイトルが空文字' => [['title' => ''], 'title'],
            'タイトルが数値以外の配列' => [['title' => ['a']], 'title'],
            'タイトルが長すぎる' => [['title' => str_repeat('a', 256)], 'title'],
            '説明が長すぎる' => [['title' => 'ok', 'description' => str_repeat('a', 2001)], 'description'],
            '優先度が0' => [['title' => 'ok', 'priority' => 0], 'priority'],
            '優先度が6' => [['title' => 'ok', 'priority' => 6], 'priority'],
            '優先度が文字列' => [['title' => 'ok', 'priority' => 'high'], 'priority'],
            '期限の形式が不正' => [['title' => 'ok', 'due_date' => '2026/11/01'], 'due_date'],
            '期限が日付でない' => [['title' => 'ok', 'due_date' => 'tomorrow'], 'due_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidStorePayloadProvider')]
    public function test_store_validation(array $payload, string $field): void
    {
        $this->repository->shouldNotReceive('create');

        $this->postJson('/api/tasks', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_update(): void
    {
        $task = $this->makeTask();
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($task);
        $this->repository->shouldReceive('update')
            ->once()
            ->with($task, ['title' => 'renamed', 'status' => TaskStatus::Doing])
            ->andReturnUsing(fn (Task $task, array $attributes) => $task->fill($attributes));

        $this->patchJson('/api/tasks/1', ['title' => 'renamed', 'status' => 'doing'])
            ->assertOk()
            ->assertJsonPath('data.title', 'renamed')
            ->assertJsonPath('data.status', 'doing');
    }

    #[DataProvider('invalidTransitionProvider')]
    public function test_update_rejects_invalid_transition(TaskStatus $from, string $to): void
    {
        $this->repository->shouldReceive('find')->once()->andReturn($this->makeTask(['status' => $from]));
        $this->repository->shouldNotReceive('update');

        $this->patchJson('/api/tasks/1', ['status' => $to])
            ->assertUnprocessable()
            ->assertJsonPath('from', $from->value)
            ->assertJsonPath('to', $to);
    }

    /**
     * @return array<string, array{TaskStatus, string}>
     */
    public static function invalidTransitionProvider(): array
    {
        return [
            'todo -> done' => [TaskStatus::Todo, 'done'],
            'done -> todo' => [TaskStatus::Done, 'todo'],
            'done -> doing' => [TaskStatus::Done, 'doing'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidUpdatePayloadProvider(): array
    {
        return [
            'タイトルが空文字' => [['title' => ''], 'title'],
            'タイトルが長すぎる' => [['title' => str_repeat('a', 256)], 'title'],
            '不明なステータス' => [['status' => 'archived'], 'status'],
            '優先度が範囲外' => [['priority' => 10], 'priority'],
            '期限の形式が不正' => [['due_date' => '01-11-2026'], 'due_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidUpdatePayloadProvider')]
    public function test_update_validation(array $payload, string $field): void
    {
        $this->repository->shouldNotReceive('find');
        $this->repository->shouldNotReceive('update');

        $this->patchJson('/api/tasks/1', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_update_returns_404_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->andReturnNull();

        $this->patchJson('/api/tasks/1', ['title' => 'x'])->assertNotFound();
    }

    public function test_destroy(): void
    {
        $task = $this->makeTask();
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($task);
        $this->repository->shouldReceive('delete')->once()->with($task);

        $this->deleteJson('/api/tasks/1')->assertNoContent();
    }

    public function test_destroy_returns_404_when_missing(): void
    {
        $this->repository->shouldReceive('find')->once()->andReturnNull();
        $this->repository->shouldNotReceive('delete');

        $this->deleteJson('/api/tasks/1')->assertNotFound();
    }

    public function test_summary(): void
    {
        $this->repository->shouldReceive('countByStatus')->once()->andReturn(['todo' => 1, 'done' => 3]);
        $this->repository->shouldReceive('list')->once()->andReturn(collect());

        $this->getJson('/api/tasks/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_status.doing', 0)
            ->assertJsonPath('data.completion_rate', 75);
    }
}

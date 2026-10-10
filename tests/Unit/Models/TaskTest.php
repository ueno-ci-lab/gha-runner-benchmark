<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskTest extends TestCase
{
    /**
     * @return array<string, array{?string, TaskStatus, bool}>
     */
    public static function overdueProvider(): array
    {
        return [
            '期限なし' => [null, TaskStatus::Todo, false],
            '期限が昨日・未着手' => ['2026-10-09', TaskStatus::Todo, true],
            '期限が昨日・進行中' => ['2026-10-09', TaskStatus::Doing, true],
            '期限が昨日・完了' => ['2026-10-09', TaskStatus::Done, false],
            '期限が今日' => ['2026-10-10', TaskStatus::Todo, false],
            '期限が明日' => ['2026-10-11', TaskStatus::Todo, false],
            '期限が1年前' => ['2025-10-10', TaskStatus::Doing, true],
        ];
    }

    #[DataProvider('overdueProvider')]
    public function test_is_overdue(?string $dueDate, TaskStatus $status, bool $expected): void
    {
        $task = new Task(['due_date' => $dueDate, 'status' => $status]);

        $this->assertSame($expected, $task->isOverdue(Carbon::parse('2026-10-10 15:00:00')));
    }

    public function test_default_attributes(): void
    {
        $task = new Task(['title' => 'example']);

        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame(3, $task->priority);
    }

    public function test_casts(): void
    {
        $task = new Task(['status' => 'doing', 'priority' => '5', 'due_date' => '2026-12-31']);

        $this->assertSame(TaskStatus::Doing, $task->status);
        $this->assertSame(5, $task->priority);
        $this->assertInstanceOf(Carbon::class, $task->due_date);
        $this->assertSame('2026-12-31', $task->due_date->toDateString());
    }
}

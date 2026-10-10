<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    /**
     * @return array<string, array{TaskStatus, TaskStatus, bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            'todo -> todo' => [TaskStatus::Todo, TaskStatus::Todo, false],
            'todo -> doing' => [TaskStatus::Todo, TaskStatus::Doing, true],
            'todo -> done' => [TaskStatus::Todo, TaskStatus::Done, false],
            'doing -> todo' => [TaskStatus::Doing, TaskStatus::Todo, true],
            'doing -> doing' => [TaskStatus::Doing, TaskStatus::Doing, false],
            'doing -> done' => [TaskStatus::Doing, TaskStatus::Done, true],
            'done -> todo' => [TaskStatus::Done, TaskStatus::Todo, false],
            'done -> doing' => [TaskStatus::Done, TaskStatus::Doing, false],
            'done -> done' => [TaskStatus::Done, TaskStatus::Done, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function test_can_transition_to(TaskStatus $from, TaskStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{TaskStatus, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'todo' => [TaskStatus::Todo, '未着手'],
            'doing' => [TaskStatus::Doing, '進行中'],
            'done' => [TaskStatus::Done, '完了'],
        ];
    }

    #[DataProvider('labelProvider')]
    public function test_label(TaskStatus $status, string $expected): void
    {
        $this->assertSame($expected, $status->label());
    }

    public function test_done_is_terminal(): void
    {
        $this->assertSame([], TaskStatus::Done->allowedTransitions());
    }

    public function test_values_are_stable(): void
    {
        $this->assertSame(['todo', 'doing', 'done'], array_column(TaskStatus::cases(), 'value'));
    }
}

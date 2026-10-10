<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    /**
     * このステータスから遷移可能なステータス一覧。
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Todo => [self::Doing],
            self::Doing => [self::Todo, self::Done],
            self::Done => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Todo => '未着手',
            self::Doing => '進行中',
            self::Done => '完了',
        };
    }
}

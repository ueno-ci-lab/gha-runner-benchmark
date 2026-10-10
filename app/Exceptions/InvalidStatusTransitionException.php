<?php

namespace App\Exceptions;

use App\Enums\TaskStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(
        public readonly TaskStatus $from,
        public readonly TaskStatus $to,
    ) {
        parent::__construct("ステータスを {$from->value} から {$to->value} に変更することはできません。");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'from' => $this->from->value,
            'to' => $this->to->value,
        ], 422);
    }
}

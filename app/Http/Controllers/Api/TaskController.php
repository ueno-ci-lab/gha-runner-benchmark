<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ]);

        $status = isset($validated['status']) ? TaskStatus::from($validated['status']) : null;

        return TaskResource::collection($this->service->list($status));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->service->create($request->validated());

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function show(int $task): TaskResource
    {
        return new TaskResource($this->service->findOrFail($task));
    }

    public function update(UpdateTaskRequest $request, int $task): TaskResource
    {
        return new TaskResource($this->service->update($task, $request->validated()));
    }

    public function destroy(int $task): Response
    {
        $this->service->delete($task);

        return response()->noContent();
    }

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->service->summary()]);
    }
}

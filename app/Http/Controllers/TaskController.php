<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\TaskList;
use App\Services\TaskService;
use App\Support\KanbanPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Thẻ (task): thao tác NỀN do Vue gọi bằng fetch (session + CSRF, không token/API), trả JSON hoặc 204.
 * Route nằm trong routes/web.php, lồng NÔNG: thêm thẻ nằm dưới /lists/{list}/tasks, thao tác trên một thẻ
 * cụ thể nằm ở /tasks/{task}. Mọi action đều authorize bằng TaskPolicy (trong FormRequest hoặc
 * $this->authorize) trên đối tượng thật lấy từ route model binding => đổi id trên URL không vượt được
 * quyền (IDOR). Viewer/khách nhận 403 khi sửa.
 *
 * Phản hồi dùng KanbanPayload nên cùng cấu trúc với dữ liệu của trang kanban.
 */
class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    /** Thêm nhanh thẻ vào cuối cột. 201 + { task } (cấu trúc thẻ trên bảng). */
    public function store(StoreTaskRequest $request, TaskList $list): JsonResponse
    {
        $task = $this->tasks->create($list, $request->user(), $request->validated('title'));

        return response()->json(['task' => KanbanPayload::task($this->tasks->forPayload($task))], 201);
    }

    /** Chi tiết thẻ cho modal (kèm mô tả và tên cột). Ai xem được bảng đều xem được. 200 + { task }. */
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(['task' => KanbanPayload::taskDetail($this->tasks->forPayload($task))]);
    }

    /** Sửa thẻ. 200 + { task } (chi tiết, gồm mô tả). */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->tasks->update($task, $request->validated());

        return response()->json(['task' => KanbanPayload::taskDetail($this->tasks->forPayload($task))]);
    }

    /** Lưu trữ thẻ. 204. */
    public function archive(Task $task): Response
    {
        $this->authorize('archive', $task);

        $this->tasks->archive($task);

        return response()->noContent();
    }

    /** Xóa thẻ. 204. */
    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task);

        return response()->noContent();
    }
}

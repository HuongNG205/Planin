<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveTaskRequest;
use App\Models\Task;
use App\Models\TaskList;
use App\Services\ReorderService;
use Illuminate\Http\JsonResponse;

/**
 * Kéo thả THẺ (bước 10): thao tác NỀN do Vue gọi bằng fetch (session + CSRF, không token/API).
 * Route nằm trong routes/web.php: PATCH /tasks/{task}/move. Thẻ lấy từ route model binding (đã xóa mềm => 404).
 *
 * Quyền: MoveTaskRequest kiểm tra TaskPolicy@update trên thẻ; ở đây kiểm tra TaskPolicy@move với cột đích
 * (quyền sửa bảng của cột đích, cột đích chưa lưu trữ). Chuyển sang bảng khác => 422 (ReorderService).
 */
class TaskMoveController extends Controller
{
    public function __construct(private readonly ReorderService $reorder)
    {
    }

    /** 200 + { list_id, positions: { taskId: position } } (position mới để Vue đồng bộ). */
    public function __invoke(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $target = TaskList::query()->findOrFail((int) $request->validated('to_list_id'));

        $this->authorize('move', [$task, $target]);

        return response()->json(
            $this->reorder->moveTask($task, $target, (int) $request->validated('position')),
        );
    }
}

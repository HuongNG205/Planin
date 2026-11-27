<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveTaskListRequest;
use App\Models\TaskList;
use App\Services\ReorderService;
use Illuminate\Http\JsonResponse;

/**
 * Kéo thả CỘT đổi thứ tự (bước 10): thao tác NỀN do Vue gọi bằng fetch (session + CSRF, không token/API).
 * Route nằm trong routes/web.php: PATCH /lists/{list}/move. Cột lấy từ route model binding (đã xóa mềm => 404).
 * Quyền do MoveTaskListRequest kiểm tra (TaskListPolicy@update).
 */
class TaskListReorderController extends Controller
{
    public function __construct(private readonly ReorderService $reorder)
    {
    }

    /** 200 + { positions: { listId: position } } (position mới để Vue đồng bộ). */
    public function __invoke(MoveTaskListRequest $request, TaskList $list): JsonResponse
    {
        return response()->json(
            $this->reorder->moveList($list, (int) $request->validated('position')),
        );
    }
}

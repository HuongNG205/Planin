<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskListRequest;
use App\Http\Requests\UpdateTaskListRequest;
use App\Models\Board;
use App\Models\TaskList;
use App\Services\TaskListService;
use App\Support\KanbanPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Cột (task list): thao tác NỀN do Vue gọi bằng fetch (session + CSRF, không token/API), trả JSON hoặc 204.
 * Route nằm trong routes/web.php, lồng NÔNG: thêm cột nằm dưới /boards/{board}/lists, thao tác trên một
 * cột cụ thể nằm ở /lists/{list}. Mọi action đều authorize bằng TaskListPolicy (trong FormRequest hoặc
 * $this->authorize) trên đối tượng thật lấy từ route model binding => đổi id trên URL không vượt được
 * quyền (IDOR). Viewer/khách nhận 403.
 *
 * Controller chỉ nhận Request đã validate/authorize và gọi TaskListService.
 */
class TaskListController extends Controller
{
    public function __construct(private readonly TaskListService $lists)
    {
    }

    /** Thêm cột vào cuối bảng. 201 + { list: {id, name, position} } (cột mới chưa có thẻ). */
    public function store(StoreTaskListRequest $request, Board $board): JsonResponse
    {
        $list = $this->lists->create($board, $request->validated('name'));

        return response()->json(['list' => KanbanPayload::listSummary($list)], 201);
    }

    /** Đổi tên cột. 200 + { list: {id, name, position} }. */
    public function update(UpdateTaskListRequest $request, TaskList $list): JsonResponse
    {
        $list = $this->lists->rename($list, $request->validated('name'));

        return response()->json(['list' => KanbanPayload::listSummary($list)]);
    }

    /** Lưu trữ cột. 204. */
    public function archive(TaskList $list): Response
    {
        $this->authorize('archive', $list);

        $this->lists->archive($list);

        return response()->noContent();
    }

    /** Xóa cột. 204. */
    public function destroy(TaskList $list): Response
    {
        $this->authorize('delete', $list);

        $this->lists->delete($list);

        return response()->noContent();
    }
}

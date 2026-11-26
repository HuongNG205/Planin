<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoardRequest;
use App\Http\Requests\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Workspace;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bảng (board): index, store, show, update, archive, restore, destroy + trang bảng đã lưu trữ.
 *
 * Route lồng NÔNG: những action cần workspace (index, archived, store) nằm dưới
 * /workspaces/{workspace}/boards; những action trên một bảng cụ thể nằm ở /boards/{board}
 * (nên không cần scopeBindings). Mọi action đều authorize bằng BoardPolicy (hoặc FormRequest)
 * trên đối tượng thật lấy từ route model binding => đổi id trên URL không vượt được quyền (IDOR).
 *
 * Controller chỉ nhận Request đã validate/authorize và gọi BoardService.
 */
class BoardController extends Controller
{
    public function __construct(private readonly BoardService $boards)
    {
    }

    /**
     * Danh sách bảng của workspace. Giao diện danh sách (kèm sidebar, modal) do
     * WorkspaceController@show dựng, nên URL này chỉ chuyển hướng về đó để có địa chỉ ổn định.
     */
    public function index(Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        return redirect()->route('workspaces.show', $workspace);
    }

    /** Trang "Bảng đã lưu trữ" của workspace: xem, khôi phục, xóa (chỉ admin). */
    public function archived(Workspace $workspace): View
    {
        $this->authorize('viewArchived', [Board::class, $workspace]);

        return view('boards.archived', [
            'workspace' => $workspace,
            // Eager load người tạo (có thể null) để không bị N+1.
            'boards' => $workspace->archivedBoards()->with('user:id,name')->get(),
        ]);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->boards->create($workspace, $request->user(), $request->validated());

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Tạo bảng thành công!');
    }

    /**
     * Trang bảng kanban: khung (tiêu đề, nền, thao tác quản lý) + cột và thẻ hiển thị bằng Vue.
     * Cột/thẻ đang hoạt động được eager load một lần rồi truyền xuống Vue qua props (không gọi API).
     */
    public function show(Request $request, Board $board): View
    {
        $this->authorize('view', $board);

        // Policy dùng $board->workspace; nạp một lần cho cả request (cùng cache vai trò).
        $board->loadMissing(['workspace', 'user']);

        $this->boards->recordRecent($board);
        $this->boards->loadKanban($board);

        // Vai trò của người xem (null = khách xem bảng public) và quyền sửa cột/thẻ, để Vue ẩn nút sửa
        // với viewer/khách. Quyền sửa lấy từ BoardPolicy::editCards (cùng quy tắc với Policy của cột/thẻ).
        $user = $request->user();
        $role = $board->workspace->roleOf($user);

        return view('boards.show', [
            'board' => $board,
            'workspace' => $board->workspace,
            'isStarred' => $this->boards->isStarredBy($board, $user),
            'kanbanProps' => $this->boards->kanbanProps($board, $role, $user->can('editCards', $board)),
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $this->boards->update($board, $request->validated());

        return redirect()->route('boards.show', $board)
            ->with('success', 'Đã cập nhật bảng.');
    }

    public function archive(Board $board): RedirectResponse
    {
        $this->authorize('archive', $board);

        $this->boards->archive($board);

        return redirect()->route('workspaces.show', $board->workspace_id)
            ->with('success', 'Đã lưu trữ bảng "' . $board->name . '".');
    }

    public function restore(Board $board): RedirectResponse
    {
        $this->authorize('restore', $board);

        $this->boards->restore($board);

        return redirect()->route('boards.archived', $board->workspace_id)
            ->with('success', 'Đã khôi phục bảng "' . $board->name . '".');
    }

    public function destroy(Board $board): RedirectResponse
    {
        $this->authorize('delete', $board);

        $wasArchived = $board->isArchived();

        $this->boards->delete($board);

        // Xóa từ trang lưu trữ thì ở lại trang lưu trữ, còn lại về trang workspace.
        $target = $wasArchived
            ? route('boards.archived', $board->workspace_id)
            : route('workspaces.show', $board->workspace_id);

        return redirect($target)->with('success', 'Đã xóa bảng "' . $board->name . '".');
    }
}

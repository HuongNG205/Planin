<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\BoardService;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Workspace: index, show, store, update, destroy. (Thành viên/rời workspace: WorkspaceMemberController;
 * bảng: BoardController.) Controller chỉ nhận Request đã validate/authorize và gọi WorkspaceService.
 * Danh sách luôn truy vấn theo workspace_members (kể cả workspace được mời).
 */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly BoardService $boards,
    ) {
    }

    /** Trang làm việc: workspace mới nhất của người dùng (không có thì hiện trạng thái rỗng). */
    public function index(Request $request): View
    {
        $workspaces = $this->workspaces->forUser($request->user());

        return $this->dashboard($request, $workspaces, $workspaces->first());
    }

    /** Trang làm việc ứng với một workspace cụ thể. */
    public function show(Request $request, Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        $workspaces = $this->workspaces->forUser($request->user());

        return $this->dashboard($request, $workspaces, $workspaces->firstWhere('id', $workspace->id) ?? $workspace);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->create($request->user(), $request->validated());

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Tạo Không gian làm việc thành công!');
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaces->update($workspace, $request->validated());

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Đã cập nhật Không gian làm việc.');
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $this->workspaces->delete($workspace);

        return redirect()->route('workspaces.index')
            ->with('success', 'Đã xóa Không gian làm việc.');
    }

    /**
     * Dữ liệu cho trang làm việc (view 'board'): sidebar cần danh sách workspace,
     * phần chính cần các bảng của workspace đang xem.
     *
     * @param  Collection<int, Workspace>  $workspaces
     */
    private function dashboard(Request $request, Collection $workspaces, ?Workspace $current): View
    {
        $user = $request->user();
        $role = $current?->roleOf($user);
        $isMember = $role !== null;
        $members = collect();
        $boards = collect();
        $starredIds = [];

        if ($current) {
            // Một truy vấn lọc theo quyền xem (Board::scopeVisibleTo) cho cả thành viên lẫn khách:
            // admin thấy hết; member/viewer thấy bảng workspace/public + bảng riêng của mình;
            // người ngoài (workspace công khai) chỉ thấy bảng public. Không gọi Policy từng bảng.
            $boards = $current->boards()->visibleTo($user, $role)->get();
            $current->setRelation('boards', $boards);
            $starredIds = $this->boards->starredIdsAmong($user, $boards);

            if ($isMember) {
                $members = $this->sortedMembers($current);
            }
        }

        return view('board', [
            'workspaces' => $workspaces,
            'currentWorkspace' => $current,
            'currentRole' => $role,
            'isOwner' => $current?->isOwnedBy($user) ?? false,
            'isMember' => $isMember,
            'members' => $members,
            'starredBoardIds' => $starredIds,
            // Sidebar: mục "Đã gắn sao" và "Gần đây".
            'starredBoards' => $this->boards->starredFor($user),
            'recentBoards' => $this->boards->recentFor($user),
        ]);
    }

    /** Chủ sở hữu trước, rồi admin > member > viewer, cuối cùng theo tên. */
    private function sortedMembers(Workspace $workspace): Collection
    {
        $order = ['admin' => 0, 'member' => 1, 'viewer' => 2];

        return $workspace->members()->orderBy('users.name')->get()
            ->sortBy(fn ($u) => [$workspace->isOwnedBy($u) ? 0 : 1, $order[$u->pivot->role] ?? 9])
            ->values();
    }
}

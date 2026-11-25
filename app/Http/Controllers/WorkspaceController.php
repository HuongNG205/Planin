<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Support\BoardBackground;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Workspace: index, show, store, update, destroy. (Thành viên/rời workspace: WorkspaceMemberController.)
 * Controller chỉ nhận Request đã validate/authorize và gọi WorkspaceService.
 * Danh sách luôn truy vấn theo workspace_members (kể cả workspace được mời).
 */
class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
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
     * TẠM THỜI: tạo bảng. Bước 6 sẽ chuyển sang BoardController/BoardService/BoardPolicy.
     * Đã bỏ kiểm tra theo user_id của người tạo; quyền nay dựa vào vai trò trong workspace.
     */
    public function storeBoard(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'name' => 'required|string|max:150',
            // Chỉ nhận preset hoặc mã màu hợp lệ (chặn chèn CSS); không gửi = nền mặc định.
            'background' => ['nullable', 'string', 'max:255', BoardBackground::rule()],
            'visibility' => ['nullable', Rule::in(BoardVisibility::values())],
        ]);

        $workspace = Workspace::findOrFail($validated['workspace_id']);

        $this->authorize('manageBoards', $workspace);

        Board::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'background' => $validated['background'] ?? BoardBackground::default(),
            'visibility' => $validated['visibility'] ?? BoardVisibility::default()->value,
        ]);

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Tạo bảng thành công!');
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
        $isMember = $current?->isMember($user) ?? false;
        $members = collect();

        if ($current && $isMember) {
            // Chỉ nạp bảng của workspace đang xem (không nạp cho tất cả workspace).
            $current->loadMissing('boards');
            $members = $this->sortedMembers($current);
        } elseif ($current) {
            // Người ngoài xem workspace công khai: chỉ thấy các bảng công khai.
            $current->setRelation(
                'boards',
                $current->boards()->where('visibility', BoardVisibility::Public->value)->get()
            );
        }

        return view('board', [
            'workspaces' => $workspaces,
            'currentWorkspace' => $current,
            'currentRole' => $current?->roleOf($user),
            'isOwner' => $current?->isOwnedBy($user) ?? false,
            'isMember' => $isMember,
            'members' => $members,
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

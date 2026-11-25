<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thành viên workspace: mời, đổi vai trò, xóa, rời.
 * Toàn bộ route nằm sau middleware 'workspace.member' (phải là thành viên) và được
 * kiểm tra tiếp bằng WorkspacePolicy; nghiệp vụ nằm ở MemberService.
 */
class WorkspaceMemberController extends Controller
{
    public function __construct(private readonly MemberService $members)
    {
    }

    public function store(InviteMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $user = $this->members->invite($workspace, $request->validated('email'), $request->validated('role'));

        return $this->backToMembers($workspace, "Đã thêm {$user->name} vào không gian làm việc.");
    }

    public function update(UpdateMemberRoleRequest $request, Workspace $workspace, User $member): RedirectResponse
    {
        abort_unless($workspace->isMember($member), 404);

        $this->members->changeRole($workspace, $member, WorkspaceRole::from($request->validated('role')));

        return $this->backToMembers($workspace, 'Đã cập nhật vai trò thành viên.');
    }

    public function destroy(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('removeMember', [$workspace, $member]);
        abort_unless($workspace->isMember($member), 404);

        $this->members->remove($workspace, $member);

        // Admin tự xóa chính mình: không còn quyền xem workspace (nếu riêng tư) nên về trang chung.
        if ($member->is($request->user())) {
            return redirect()->route('workspaces.index')
                ->with('success', 'Bạn đã rời khỏi Không gian làm việc "' . $workspace->name . '".');
        }

        return $this->backToMembers($workspace, "Đã xóa {$member->name} khỏi không gian làm việc.");
    }

    /** Thành viên (không phải chủ sở hữu) tự rời workspace. */
    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('leave', $workspace);

        $this->members->leave($workspace, $request->user());

        return redirect()->route('workspaces.index')
            ->with('success', 'Bạn đã rời khỏi Không gian làm việc "' . $workspace->name . '".');
    }

    /** Quay lại trang workspace và mở lại modal Thành viên. */
    private function backToMembers(Workspace $workspace, string $message): RedirectResponse
    {
        return redirect()->route('workspaces.show', $workspace)
            ->with('success', $message)
            ->with('open_modal', 'membersModal');
    }
}

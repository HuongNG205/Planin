<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nghiệp vụ THÀNH VIÊN workspace: mời theo email, đổi vai trò, xóa, rời.
 * Quyền do WorkspacePolicy/FormRequest kiểm tra trước; các quy tắc dưới đây là lớp bảo vệ
 * dữ liệu (chủ sở hữu bất khả xâm phạm, luôn còn ít nhất 1 admin).
 * Dùng attach/detach/updateExistingPivot (workspace_members không có soft delete).
 */
class MemberService
{
    /**
     * Mời một người dùng đã có tài khoản vào workspace (thêm thẳng, không có bước chấp nhận).
     *
     * @throws ValidationException lỗi vào error bag 'inviteMember'
     */
    public function invite(Workspace $workspace, string $email, ?string $role = null): User
    {
        $user = User::where('email', $email)->first(); // user đã xóa mềm tự bị loại

        if (! $user) {
            throw $this->inviteError('Không tìm thấy tài khoản nào với email này.');
        }

        $roleValue = (WorkspaceRole::tryFrom((string) $role) ?? WorkspaceRole::default())->value;

        try {
            DB::transaction(function () use ($workspace, $user, $roleValue) {
                if ($workspace->isMember($user)) {
                    throw $this->inviteError('Người này đã là thành viên của không gian làm việc.');
                }

                $workspace->members()->attach($user->getKey(), ['role' => $roleValue]);
            });
        } catch (UniqueConstraintViolationException) {
            // Hai yêu cầu mời cùng lúc: unique (workspace_id, user_id) chặn ở DB.
            throw $this->inviteError('Người này đã là thành viên của không gian làm việc.');
        }

        $workspace->forgetRoleCache();

        return $user;
    }

    /**
     * @throws AuthorizationException  đổi role của chủ sở hữu hoặc của người không phải thành viên
     * @throws ValidationException     giáng cấp admin cuối cùng (error bag 'updateMemberRole')
     */
    public function changeRole(Workspace $workspace, User $target, WorkspaceRole $role): void
    {
        $this->assertManageable($workspace, $target);

        $current = $workspace->roleOf($target);

        if ($current === $role) {
            return;
        }

        if ($current === WorkspaceRole::Admin && $this->adminCount($workspace) <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Không gian làm việc phải còn ít nhất một quản trị viên.',
            ])->errorBag('updateMemberRole');
        }

        $workspace->members()->updateExistingPivot($target->getKey(), ['role' => $role->value]);
        $workspace->forgetRoleCache();
    }

    /** Admin xóa một thành viên khỏi workspace. */
    public function remove(Workspace $workspace, User $target): void
    {
        $this->assertManageable($workspace, $target);
        $this->detachMember($workspace, $target);
    }

    /** Thành viên tự rời workspace. Chủ sở hữu không được rời (chỉ có thể xóa workspace). */
    public function leave(Workspace $workspace, User $user): void
    {
        if ($workspace->isOwnedBy($user)) {
            throw new AuthorizationException('Chủ sở hữu không thể rời khỏi không gian làm việc của mình.');
        }

        if (! $workspace->isMember($user)) {
            throw new AuthorizationException('Bạn không phải thành viên của không gian làm việc này.');
        }

        $this->detachMember($workspace, $user);
    }

    /** Không đụng vào chủ sở hữu; $target phải là thành viên; không để workspace hết admin. */
    private function assertManageable(Workspace $workspace, User $target): void
    {
        if ($workspace->isOwnedBy($target)) {
            throw new AuthorizationException('Không thể thay đổi chủ sở hữu của không gian làm việc.');
        }

        if (! $workspace->isMember($target)) {
            throw new AuthorizationException('Người này không phải thành viên của không gian làm việc.');
        }
    }

    private function adminCount(Workspace $workspace): int
    {
        return $workspace->members()->wherePivot('role', WorkspaceRole::Admin->value)->count();
    }

    /**
     * Gỡ thành viên VÀ dọn dữ liệu gắn với quyền truy cập của họ trong workspace này:
     * người được giao ở các thẻ, và gắn sao các bảng. Dùng truy vấn thô để dọn cả
     * thẻ/bảng đã xóa mềm hoặc lưu trữ.
     */
    private function detachMember(Workspace $workspace, User $user): void
    {
        if ($workspace->roleOf($user) === WorkspaceRole::Admin && $this->adminCount($workspace) <= 1) {
            throw new AuthorizationException('Không gian làm việc phải còn ít nhất một quản trị viên.');
        }

        DB::transaction(function () use ($workspace, $user) {
            $workspace->members()->detach($user->getKey());

            DB::table('task_assignees')
                ->where('user_id', $user->getKey())
                ->whereIn('task_id', function ($query) use ($workspace) {
                    $query->select('tasks.id')
                        ->from('tasks')
                        ->join('task_lists', 'task_lists.id', '=', 'tasks.task_list_id')
                        ->join('boards', 'boards.id', '=', 'task_lists.board_id')
                        ->where('boards.workspace_id', $workspace->getKey());
                })
                ->delete();

            DB::table('board_stars')
                ->where('user_id', $user->getKey())
                ->whereIn('board_id', function ($query) use ($workspace) {
                    $query->select('id')->from('boards')->where('workspace_id', $workspace->getKey());
                })
                ->delete();
        });

        $workspace->forgetRoleCache();
    }

    private function inviteError(string $message): ValidationException
    {
        return ValidationException::withMessages(['email' => $message])->errorBag('inviteMember');
    }
}

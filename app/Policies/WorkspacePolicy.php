<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * Quyền với Workspace. Dựa vào workspace_members.role + quyền "chủ sở hữu"
 * (workspaces.user_id); KHÔNG dùng users.role.
 *
 *   view          : mọi thành viên (viewer trở lên)
 *   update/delete : chỉ chủ sở hữu
 *   leave         : thành viên không phải chủ sở hữu
 *   manageMembers : admin của workspace (chủ sở hữu luôn là admin) - dùng ở bước 5
 *   manageBoards  : admin của workspace - tạm dùng cho tạo bảng, bước 6 chuyển sang BoardPolicy
 */
class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        // Ai đã đăng nhập cũng được xem DANH SÁCH của chính mình (lọc theo workspace_members ở service).
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->isMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user);
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->isMember($user) && ! $workspace->isOwnedBy($user);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canManage() ?? false;
    }

    public function manageBoards(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canManage() ?? false;
    }
}

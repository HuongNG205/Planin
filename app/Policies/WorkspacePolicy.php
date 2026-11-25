<?php

namespace App\Policies;

use App\Enums\WorkspaceVisibility;
use App\Models\User;
use App\Models\Workspace;

/**
 * Ma trận quyền (dựa vào workspace_members.role + quyền "chủ sở hữu" workspaces.user_id;
 * KHÔNG dùng users.role):
 *
 *   Người ngoài   : chỉ XEM workspace "công khai" (và các bảng công khai của nó)
 *   viewer        : xem
 *   member        : xem + thao tác cột/thẻ (editCards)
 *   admin         : member + quản lý bảng (BoardPolicy) + quản lý thành viên (manageMembers)
 *   chủ sở hữu    : admin + sửa/xóa workspace (luôn là admin, không bị giáng/xóa)
 */
class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        // Ai đã đăng nhập cũng được xem DANH SÁCH của chính mình (lọc theo workspace_members ở service).
        return true;
    }

    /** Thành viên, hoặc bất kỳ ai đã đăng nhập nếu workspace công khai. */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->isMember($user)
            || $workspace->visibility === WorkspaceVisibility::Public->value;
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

    /** Thành viên (không phải chủ sở hữu) tự rời workspace. */
    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->isMember($user) && ! $workspace->isOwnedBy($user);
    }

    /** Xem danh sách thành viên: chỉ thành viên. */
    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isMember($user);
    }

    /** Mời thành viên: admin của workspace. */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canManage() ?? false;
    }

    /** Đổi vai trò của $target: admin, trừ chủ sở hữu (không bị giáng cấp). */
    public function updateMemberRole(User $user, Workspace $workspace, User $target): bool
    {
        return $this->manageMembers($user, $workspace) && ! $workspace->isOwnedBy($target);
    }

    /** Xóa $target khỏi workspace: admin, trừ chủ sở hữu. (Tự rời dùng 'leave'.) */
    public function removeMember(User $user, Workspace $workspace, User $target): bool
    {
        return $this->manageMembers($user, $workspace) && ! $workspace->isOwnedBy($target);
    }

    /** Thao tác cột/thẻ (thêm, sửa, kéo thả, bình luận): member trở lên. Dùng cho các Policy bước 8-12. */
    public function editCards(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canEditCards() ?? false;
    }
}

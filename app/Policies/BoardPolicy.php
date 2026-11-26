<?php

namespace App\Policies;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

/**
 * Ma trận quyền của BẢNG. Dựa vào workspace_members.role (qua Workspace::roleOf) + boards.visibility;
 * KHÔNG dùng users.role. Chưa có bảng board_members nên "riêng tư" gắn với người tạo + admin.
 *
 * XEM (view), bảng đang hoạt động:
 *   visibility = private    : người tạo bảng (còn là thành viên) + admin của workspace
 *   visibility = workspace  : mọi thành viên workspace (admin/member/viewer)
 *   visibility = public     : mọi người đã đăng nhập (xem bằng link, chỉ đọc)
 * Bảng ĐÃ LƯU TRỮ: chỉ admin của workspace thấy (để khôi phục/xóa), bất kể visibility.
 *
 * TẠO / SỬA / LƯU TRỮ / KHÔI PHỤC / XÓA: admin của workspace (chủ sở hữu luôn là admin).
 *   - sửa và lưu trữ chỉ áp dụng cho bảng chưa lưu trữ; khôi phục chỉ cho bảng đã lưu trữ.
 * GẮN SAO: ai xem được bảng (và bảng chưa lưu trữ).
 *
 * SỬA CỘT/THẺ (editCards): member trở lên của workspace VÀ xem được bảng VÀ bảng chưa lưu trữ. Đây là điều kiện
 * CHUNG cho mọi thao tác nền trên bảng; TaskListPolicy (bước 8), TaskPolicy (bước 9)... đều gọi lại hàm này
 * thay vì tự viết lại, để quy tắc không bị lệch. Viewer và khách xem bảng public luôn bị từ chối.
 *
 * Phải khớp với Board::scopeVisibleTo()/scopeAccessibleBy() (lọc danh sách); có test đối chiếu.
 */
class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        // Workspace đã xóa mềm => $board->workspace là null => không ai xem được.
        $workspace = $board->workspace;

        if (! $workspace) {
            return false;
        }

        $role = $workspace->roleOf($user);

        if ($board->isArchived()) {
            return $role?->canManage() ?? false;
        }

        return match (BoardVisibility::tryFrom((string) $board->visibility)) {
            BoardVisibility::Public => true,
            BoardVisibility::Workspace => $role !== null,
            BoardVisibility::Private => $role !== null
                && ($role->canManage() || (int) $board->user_id === (int) $user->getKey()),
            default => false,
        };
    }

    /** Tạo bảng trong $workspace: admin. Gọi bằng can('create', [Board::class, $workspace]). */
    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canManage() ?? false;
    }

    /** Vào trang "Bảng đã lưu trữ" của $workspace: admin. Gọi bằng can('viewArchived', [Board::class, $workspace]). */
    public function viewArchived(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canManage() ?? false;
    }

    public function update(User $user, Board $board): bool
    {
        return ! $board->isArchived() && $this->manage($user, $board);
    }

    public function archive(User $user, Board $board): bool
    {
        return ! $board->isArchived() && $this->manage($user, $board);
    }

    public function restore(User $user, Board $board): bool
    {
        return $board->isArchived() && $this->manage($user, $board);
    }

    public function delete(User $user, Board $board): bool
    {
        return $this->manage($user, $board);
    }

    public function star(User $user, Board $board): bool
    {
        return ! $board->isArchived() && $this->view($user, $board);
    }

    /**
     * Thao tác cột/thẻ trên bảng: member trở lên, xem được bảng (vd. thành viên không thấy bảng private của
     * người khác thì cũng không sửa được), và bảng chưa lưu trữ. Gọi bằng can('editCards', $board).
     */
    public function editCards(User $user, Board $board): bool
    {
        return ! $board->isArchived()
            && $this->view($user, $board)
            && ($board->workspace?->roleOf($user)?->canEditCards() ?? false);
    }

    /** admin của workspace chứa bảng (workspace xóa mềm => false). */
    private function manage(User $user, Board $board): bool
    {
        return $board->workspace?->roleOf($user)?->canManage() ?? false;
    }
}

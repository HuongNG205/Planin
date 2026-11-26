<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\TaskList;
use App\Models\User;

/**
 * Quyền thao tác CỘT. Mọi quyền đều dựa vào BoardPolicy::editCards (member trở lên, xem được bảng,
 * bảng chưa lưu trữ) nên viewer, khách xem bảng public và người ngoài workspace đều bị từ chối.
 *
 *   create  : thêm cột vào bảng                  (gọi can('create', [TaskList::class, $board]))
 *   update  : đổi tên cột   - chỉ cột đang hoạt động
 *   archive : lưu trữ cột   - chỉ cột đang hoạt động
 *   delete  : xóa cột       - cả cột đang hoạt động lẫn đã lưu trữ (xóa từ trang lưu trữ, bước 13.3)
 *
 * Cột/bảng đã xóa mềm không còn tồn tại với route binding (404); nếu bảng bị xóa sau khi nạp thì
 * $list->board là null => từ chối.
 */
class TaskListPolicy
{
    public function create(User $user, Board $board): bool
    {
        return $user->can('editCards', $board);
    }

    public function update(User $user, TaskList $list): bool
    {
        return ! $list->isArchived() && $this->editCards($user, $list);
    }

    public function archive(User $user, TaskList $list): bool
    {
        return ! $list->isArchived() && $this->editCards($user, $list);
    }

    public function delete(User $user, TaskList $list): bool
    {
        return $this->editCards($user, $list);
    }

    private function editCards(User $user, TaskList $list): bool
    {
        $board = $list->board;

        return $board !== null && $user->can('editCards', $board);
    }
}

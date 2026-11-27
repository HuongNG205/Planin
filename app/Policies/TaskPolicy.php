<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;

/**
 * Quyền thao tác THẺ. Mọi quyền SỬA đều dựa vào BoardPolicy::editCards (member trở lên, xem được bảng,
 * bảng chưa lưu trữ) nên viewer, khách xem bảng public và người ngoài workspace đều bị từ chối.
 *
 *   view    : mở chi tiết thẻ - ai xem được bảng (viewer và khách xem bảng public cũng xem được, chỉ đọc)
 *   create  : thêm thẻ vào cột    (gọi can('create', [Task::class, $list])) - cột phải còn hoạt động
 *   update  : sửa thẻ             - thẻ và cột chứa nó phải còn hoạt động
 *   archive : lưu trữ thẻ         - như update
 *   move    : kéo thẻ sang cột $target (gọi can('move', [$task, $target])) - phải sửa được thẻ VÀ sửa được bảng
 *             của cột đích (TaskPolicy@update chỉ kiểm tra cột hiện tại của thẻ), cột đích chưa lưu trữ.
 *             Việc cột đích cùng bảng với thẻ do ReorderService kiểm tra (422).
 *   delete  : xóa thẻ             - cả thẻ đang hoạt động lẫn đã lưu trữ (xóa từ trang lưu trữ, bước 13.3)
 *
 * Thẻ/cột/bảng đã xóa mềm không còn tồn tại với route binding (404); nếu cột hoặc bảng bị xóa sau khi
 * nạp thì quan hệ trả null => từ chối.
 */
class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        $board = $this->board($task->taskList);

        return $board !== null && $user->can('view', $board);
    }

    public function create(User $user, TaskList $list): bool
    {
        return ! $list->isArchived() && $this->editCards($user, $this->board($list));
    }

    public function update(User $user, Task $task): bool
    {
        return ! $task->isArchived() && $this->editable($user, $task);
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function move(User $user, Task $task, TaskList $target): bool
    {
        return $this->update($user, $task)
            && ! $target->isArchived()
            && $this->editCards($user, $this->board($target));
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->editCards($user, $this->board($task->taskList));
    }

    /** Thẻ nằm trong cột còn hoạt động và người dùng sửa được bảng. */
    private function editable(User $user, Task $task): bool
    {
        $list = $task->taskList;

        return $list !== null
            && ! $list->isArchived()
            && $this->editCards($user, $this->board($list));
    }

    private function editCards(User $user, ?Board $board): bool
    {
        return $board !== null && $user->can('editCards', $board);
    }

    private function board(?TaskList $list): ?Board
    {
        return $list?->board;
    }
}

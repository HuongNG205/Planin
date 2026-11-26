<?php

namespace App\Services;

use App\Models\Board;
use App\Models\TaskList;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ CỘT (task list). Service không kiểm tra quyền (đã có TaskListPolicy/FormRequest); chỉ lo dữ liệu.
 *
 * Vòng đời một cột:
 *   hoạt động  --archive-->  lưu trữ (archived_at != null)
 *   hoạt động / lưu trữ  --delete-->  xóa mềm (deleted_at != null)
 *
 * Lưu trữ/xóa một cột KHÔNG lan xuống thẻ (thẻ vẫn nằm nguyên trong cột đó, giống quy ước của bảng):
 * truy vấn thẻ đi thẳng vào bảng tasks phải dùng Task::onLiveBoard() để bỏ qua thẻ của cột/bảng đã xóa.
 * Khôi phục cột đã lưu trữ làm ở bước 13.3.
 */
class TaskListService
{
    /**
     * Thêm cột vào CUỐI bảng. board_id và position do server gán, không nhận từ input.
     */
    public function create(Board $board, string $name): TaskList
    {
        return DB::transaction(function () use ($board, $name) {
            // Khóa dòng bảng để hai request thêm cột cùng lúc không cấp trùng position
            // (SQLite bỏ qua khóa, MySQL/PostgreSQL có tác dụng).
            Board::whereKey($board->getKey())->lockForUpdate()->value('id');

            return TaskList::create([
                'board_id' => $board->getKey(),
                'name' => $name,
                'position' => $this->nextPosition($board),
            ]);
        });
    }

    public function rename(TaskList $list, string $name): TaskList
    {
        $list->update(['name' => $name]);

        return $list;
    }

    /** Lưu trữ: ẩn khỏi bảng (Board::activeTaskLists), hiện ở Board::archivedTaskLists(). */
    public function archive(TaskList $list): TaskList
    {
        if (! $list->isArchived()) {
            $list->forceFill(['archived_at' => now()])->save();
        }

        return $list;
    }

    /** Xóa mềm một cột (SoftDeletes). */
    public function delete(TaskList $list): void
    {
        $list->delete();
    }

    /**
     * position kế tiếp = lớn nhất hiện có + 1, tính cả cột đã lưu trữ và đã xóa mềm để không bao giờ
     * trùng với cột sẽ được khôi phục. Bảng chưa có cột nào => 0.
     *
     * Truy vấn đi qua TaskList::query() (không qua $board->taskLists()) vì quan hệ có orderBy('position'):
     * MAX() kèm ORDER BY làm MySQL ở chế độ ONLY_FULL_GROUP_BY báo lỗi.
     */
    private function nextPosition(Board $board): int
    {
        $max = TaskList::withTrashed()->where('board_id', $board->getKey())->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}

<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ THẺ (task). Service không kiểm tra quyền (đã có TaskPolicy/FormRequest); chỉ lo dữ liệu.
 *
 * Vòng đời một thẻ:
 *   hoạt động  --archive-->  lưu trữ (archived_at != null)
 *   hoạt động / lưu trữ  --delete-->  xóa mềm (deleted_at != null)
 * Hoàn thành là trạng thái RIÊNG (completed_at), không liên quan lưu trữ.
 * Khôi phục thẻ đã lưu trữ làm ở bước 13.3.
 */
class TaskService
{
    /**
     * Thêm thẻ vào CUỐI cột. task_list_id, user_id (người tạo) và position do server gán, không nhận từ input.
     * Các trường còn lại lấy mặc định của DB (độ ưu tiên 'medium', chưa hoàn thành, không hạn chót).
     */
    public function create(TaskList $list, User $creator, string $title): Task
    {
        return DB::transaction(function () use ($list, $creator, $title) {
            // Khóa dòng cột để hai request thêm thẻ cùng lúc không cấp trùng position
            // (SQLite bỏ qua khóa, MySQL/PostgreSQL có tác dụng).
            TaskList::whereKey($list->getKey())->lockForUpdate()->value('id');

            return Task::create([
                'task_list_id' => $list->getKey(),
                'user_id' => $creator->getKey(),
                'title' => $title,
                'position' => $this->nextPosition($list),
            ]);
        });
    }

    /**
     * Sửa thẻ. CHỈ áp dụng các khóa có mặt trong $data (khóa vắng = giữ nguyên):
     *   title (string), description (?string), due_date (?string 'Y-m-d\TH:i', null = bỏ hạn chót),
     *   completed (bool; true = đặt completed_at nếu chưa có, false = bỏ).
     *
     * due_date được hiểu theo múi giờ của ứng dụng (config app.timezone), không chuyển đổi múi giờ.
     *
     * @param  array{title?: string, description?: ?string, due_date?: ?string, completed?: bool}  $data  (đã validate)
     */
    public function update(Task $task, array $data): Task
    {
        if (array_key_exists('title', $data)) {
            $task->title = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $description = $data['description'];
            // Mô tả chỉ có khoảng trắng coi như không có mô tả.
            $task->description = ($description === null || trim($description) === '') ? null : $description;
        }

        if (array_key_exists('due_date', $data)) {
            $task->due_date = $data['due_date']; // cast datetime tự parse chuỗi; null = xóa hạn chót
        }

        if (array_key_exists('completed', $data)) {
            $this->applyCompleted($task, (bool) $data['completed']);
        }

        $task->save();

        return $task;
    }

    /** Lưu trữ: ẩn khỏi cột (Task::activeTasks), hiện ở TaskList::archivedTasks(). */
    public function archive(Task $task): Task
    {
        if (! $task->isArchived()) {
            $task->forceFill(['archived_at' => now()])->save();
        }

        return $task;
    }

    /** Xóa mềm một thẻ (SoftDeletes). */
    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Nạp đủ quan hệ để dựng JSON bằng KanbanPayload (nhãn, người được giao, đếm bình luận/tệp, cột chứa thẻ).
     * Gọi trước KanbanPayload::task()/taskDetail() khi trả một thẻ lẻ (không đi qua BoardService::loadKanban).
     */
    public function forPayload(Task $task): Task
    {
        return $task->loadMissing('taskList')
            ->load(['labels', 'assignees'])
            ->loadCount(['comments', 'attachments']);
    }

    private function applyCompleted(Task $task, bool $completed): void
    {
        if ($completed) {
            $task->completed_at ??= now(); // đã hoàn thành rồi thì giữ nguyên thời điểm cũ
        } else {
            $task->completed_at = null;
        }
    }

    /**
     * position kế tiếp = lớn nhất hiện có + 1, tính cả thẻ đã lưu trữ và đã xóa mềm để không bao giờ
     * trùng với thẻ sẽ được khôi phục. Cột chưa có thẻ nào => 0.
     * Truy vấn đi qua Task::query() (không qua $list->tasks()) vì quan hệ có orderBy('position'):
     * MAX() kèm ORDER BY làm MySQL ở chế độ ONLY_FULL_GROUP_BY báo lỗi.
     */
    private function nextPosition(TaskList $list): int
    {
        $max = Task::withTrashed()->where('task_list_id', $list->getKey())->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}

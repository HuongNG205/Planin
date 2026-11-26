<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;

/**
 * Cấu trúc JSON của cột/thẻ trên trang kanban. MỘT nơi duy nhất định nghĩa hình dạng dữ liệu, dùng cho
 * cả lúc dựng trang (BoardService::kanbanProps) lẫn phản hồi của các thao tác nền (TaskListController...),
 * để Vue thay trực tiếp dữ liệu mà không phải ghép/đoán trường.
 *
 * Chỉ đưa vào các trường để HIỂN THỊ: KHÔNG có email người dùng và KHÔNG có mô tả thẻ, vì bảng
 * 'public' xem được bởi mọi người đã đăng nhập. Ngày giờ là chuỗi ISO 8601 (kèm múi giờ).
 */
final class KanbanPayload
{
    /** Cột KHÔNG kèm thẻ: dùng cho phản hồi tạo/đổi tên (Vue giữ nguyên danh sách thẻ đang có). */
    public static function listSummary(TaskList $list): array
    {
        return [
            'id' => $list->getKey(),
            'name' => $list->name,
            'position' => $list->position,
        ];
    }

    /**
     * Cột kèm các thẻ đang hoạt động. Phải eager load 'activeTasks' (cùng labels, assignees, đếm
     * comments/attachments) trước khi gọi: xem BoardService::loadKanban().
     */
    public static function list(TaskList $list): array
    {
        return self::listSummary($list) + [
            'tasks' => $list->activeTasks
                ->map(fn (Task $task) => self::task($task))
                ->values()
                ->all(),
        ];
    }

    public static function task(Task $task): array
    {
        return [
            'id' => $task->getKey(),
            'title' => $task->title,
            'position' => $task->position,
            'due_date' => $task->due_date?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'labels' => $task->labels
                ->map(fn ($label) => [
                    'id' => $label->getKey(),
                    'name' => $label->name,
                    'color' => $label->color,
                ])
                ->values()
                ->all(),
            'assignees' => $task->assignees
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'initials' => $user->initials,
                    'avatar_url' => $user->avatar_url,
                ])
                ->values()
                ->all(),
            'comments_count' => (int) $task->comments_count,
            'attachments_count' => (int) $task->attachments_count,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sắp xếp lại CỘT trong bảng và THẺ trong/giữa các cột (kéo thả, bước 10).
 * Service không kiểm tra quyền (đã có Policy/FormRequest); chỉ lo dữ liệu và các ràng buộc toàn vẹn.
 *
 * Cách tính position: KHÔNG dùng khoảng cách (gap/lexorank) mà ĐÁNH SỐ LẠI liên tục 0, 1, 2... cho mọi
 * cột (hoặc thẻ) chưa xóa của bảng (cột) / của cột chứa (thẻ), trong một DB::transaction. Chỉ ghi những dòng
 * có position đổi nên một lần kéo thường chỉ cập nhật vài dòng. Nhờ vậy:
 *   - position luôn liền mạch, kể cả khi trước đó lưu trữ/xóa để lại khoảng trống;
 *   - cột/thẻ ĐÃ LƯU TRỮ vẫn giữ nguyên vị trí tương đối của chúng và được đánh số cùng (không bao giờ
 *     trùng position với cột/thẻ đang hoạt động), nên sau này khôi phục không gây xung đột;
 *   - mọi thao tác sắp xếp trong CÙNG MỘT BẢNG được tuần tự hóa bằng khóa dòng boards (lockForUpdate),
 *     nên hai tab kéo cùng lúc không thể cấp trùng/đảo position (SQLite bỏ qua khóa, MySQL/PostgreSQL có tác dụng).
 *
 * Vị trí đích ($index) là chỉ số 0-based trong danh sách ĐANG HIỂN THỊ (chỉ gồm cột/thẻ chưa lưu trữ) SAU KHI
 * đã bỏ phần tử đang kéo ra: đúng bằng newIndex mà Vue biết sau khi thả. $index vượt quá cuối danh sách => đưa xuống cuối.
 *
 * Mọi ràng buộc được kiểm tra LẠI bên trong transaction (sau khi khóa) bằng dữ liệu mới đọc từ DB, vì Policy chạy
 * trên dữ liệu đã nạp trước đó, có thể đã cũ nếu tab khác vừa lưu trữ/xóa/chuyển thẻ.
 */
class ReorderService
{
    /**
     * Chuyển $task tới cột $target ở vị trí $index (cùng cột => đổi thứ tự trong cột).
     *
     * Ràng buộc: thẻ chưa lưu trữ; cột nguồn và cột đích chưa lưu trữ/xóa; CÙNG MỘT bảng; bảng chưa lưu trữ.
     * Vi phạm => ValidationException (422); thẻ/cột/bảng đã bị xóa => ModelNotFoundException (404).
     *
     * @return array{list_id: int, positions: array<int, int>}  position mới của mọi thẻ trong cột đích
     *                                                          (và cột nguồn nếu khác) để Vue đồng bộ.
     */
    public function moveTask(Task $task, TaskList $target, int $index): array
    {
        return DB::transaction(function () use ($task, $target, $index) {
            $this->lockBoard((int) $target->board_id);

            // Đọc lại từ DB sau khi đã khóa: không tin các model Policy đã nạp.
            $fresh = Task::query()->find($task->getKey());
            $source = $fresh ? TaskList::query()->find($fresh->task_list_id) : null;
            $dest = TaskList::query()->find($target->getKey());
            $board = $dest ? Board::query()->find($dest->board_id) : null;

            if (! $fresh || ! $source || ! $dest || ! $board) {
                throw new ModelNotFoundException();
            }

            if ((int) $source->board_id !== (int) $dest->board_id) {
                throw $this->invalid('to_list_id', 'Không thể chuyển thẻ sang một bảng khác.');
            }

            if ($board->isArchived()) {
                throw $this->invalid('to_list_id', 'Bảng đã được lưu trữ, không thể sắp xếp thẻ.');
            }

            if ($fresh->isArchived()) {
                throw $this->invalid('to_list_id', 'Thẻ đã được lưu trữ, không thể di chuyển.');
            }

            if ($source->isArchived() || $dest->isArchived()) {
                throw $this->invalid('to_list_id', 'Cột đã được lưu trữ, không thể di chuyển thẻ vào hoặc ra khỏi cột này.');
            }

            $taskId = (int) $fresh->getKey();
            $destId = (int) $dest->getKey();
            $sourceId = (int) $source->getKey();

            // Cột đích: chèn thẻ vào đúng chỗ rồi đánh số lại (thẻ được chuyển cột ngay ở bước này).
            $destOrder = $this->insertAt($this->taskRows($destId, $taskId), $taskId, $index);
            $this->applyTaskOrder($destOrder, $destId);

            $positions = array_flip($destOrder);

            // Chuyển sang cột khác: dồn lại cột nguồn cho liền mạch.
            if ($sourceId !== $destId) {
                $sourceOrder = array_map(fn (array $row) => $row['id'], $this->taskRows($sourceId, $taskId));
                $this->applyTaskOrder($sourceOrder, $sourceId);
                $positions += array_flip($sourceOrder);
            }

            return ['list_id' => $destId, 'positions' => $positions];
        });
    }

    /**
     * Đưa $list tới vị trí $index trong bảng của nó.
     *
     * Ràng buộc: cột chưa lưu trữ/xóa và bảng chưa lưu trữ/xóa.
     *
     * @return array{positions: array<int, int>}  position mới của mọi cột chưa xóa trong bảng
     */
    public function moveList(TaskList $list, int $index): array
    {
        return DB::transaction(function () use ($list, $index) {
            $this->lockBoard((int) $list->board_id);

            $fresh = TaskList::query()->find($list->getKey());
            $board = $fresh ? Board::query()->find($fresh->board_id) : null;

            if (! $fresh || ! $board) {
                throw new ModelNotFoundException();
            }

            if ($board->isArchived()) {
                throw $this->invalid('position', 'Bảng đã được lưu trữ, không thể sắp xếp cột.');
            }

            if ($fresh->isArchived()) {
                throw $this->invalid('position', 'Cột đã được lưu trữ, không thể di chuyển.');
            }

            $listId = (int) $fresh->getKey();
            $boardId = (int) $fresh->board_id;

            $rows = TaskList::query()
                ->where('board_id', $boardId)
                ->where('id', '!=', $listId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'archived_at'])
                ->map(fn (TaskList $item) => ['id' => (int) $item->getKey(), 'archived' => $item->isArchived()])
                ->all();

            $order = $this->insertAt($rows, $listId, $index);

            foreach ($order as $position => $id) {
                // Chỉ ghi dòng có position đổi.
                TaskList::query()
                    ->whereKey($id)
                    ->where('position', '!=', $position)
                    ->update(['position' => $position]);
            }

            return ['positions' => array_flip($order)];
        });
    }

    /** Khóa dòng bảng để mọi thao tác sắp xếp/thêm cột trong cùng bảng chạy lần lượt. */
    private function lockBoard(int $boardId): void
    {
        Board::query()->whereKey($boardId)->lockForUpdate()->value('id');
    }

    /**
     * Các thẻ chưa xóa của cột (kể cả đã lưu trữ), theo thứ tự, TRỪ thẻ $exceptId (thẻ đang kéo).
     * Truy vấn đi qua Task::query() để không mang theo orderBy của quan hệ.
     *
     * @return list<array{id: int, archived: bool}>
     */
    private function taskRows(int $listId, int $exceptId): array
    {
        return Task::query()
            ->where('task_list_id', $listId)
            ->where('id', '!=', $exceptId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'archived_at'])
            ->map(fn (Task $item) => ['id' => (int) $item->getKey(), 'archived' => $item->isArchived()])
            ->all();
    }

    /**
     * Chèn $movingId vào danh sách $rows (đã bỏ phần tử đang kéo) sao cho nó đứng ở vị trí $index tính
     * theo các phần tử ĐANG HIỂN THỊ (archived = false); phần tử đã lưu trữ không được đếm nhưng vẫn được
     * giữ nguyên thứ tự tương đối. $index >= số phần tử hiển thị => xuống cuối.
     *
     * @param  list<array{id: int, archived: bool}>  $rows
     * @return list<int>  thứ tự id mới (chỉ số mảng = position)
     */
    private function insertAt(array $rows, int $movingId, int $index): array
    {
        $order = [];
        $visible = 0;
        $inserted = false;

        foreach ($rows as $row) {
            if (! $inserted && ! $row['archived'] && $visible === $index) {
                $order[] = $movingId;
                $inserted = true;
            }

            $order[] = $row['id'];

            if (! $row['archived']) {
                $visible++;
            }
        }

        if (! $inserted) {
            $order[] = $movingId;
        }

        return $order;
    }

    /**
     * Gán position = chỉ số trong $order và task_list_id = $listId cho từng thẻ; chỉ ghi dòng có thay đổi.
     * Điều kiện where nằm ngay trong câu UPDATE nên không cần đọc position hiện tại trước.
     *
     * @param  list<int>  $order
     */
    private function applyTaskOrder(array $order, int $listId): void
    {
        foreach ($order as $position => $id) {
            Task::query()
                ->whereKey($id)
                ->where(fn ($query) => $query
                    ->where('position', '!=', $position)
                    ->orWhere('task_list_id', '!=', $listId))
                ->update(['position' => $position, 'task_list_id' => $listId]);
        }
    }

    private function invalid(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}

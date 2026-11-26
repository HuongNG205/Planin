<?php

namespace App\Services;

use App\Enums\BoardVisibility;
use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BoardBackground;
use App\Support\KanbanPayload;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ BẢNG (board) và vòng đời của nó. Service không kiểm tra quyền
 * (đã có BoardPolicy/FormRequest); chỉ lo dữ liệu.
 *
 * Vòng đời một bảng:
 *   hoạt động  --archive-->  lưu trữ (archived_at != null)  --restore-->  hoạt động
 *   hoạt động / lưu trữ  --delete-->  xóa mềm (deleted_at != null, không còn truy cập được)
 *
 * Xóa mềm một bảng KHÔNG lan xuống cột/thẻ: truy vấn thẻ đi thẳng vào bảng tasks (việc của tôi,
 * tìm kiếm...) phải dùng Task::onLiveBoard() để bỏ qua thẻ của bảng/cột đã xóa.
 */
class BoardService
{
    /** Khóa session lưu các bảng vừa mở (mục "Gần đây" ở sidebar). */
    private const RECENT_KEY = 'planin.recent_boards';

    private const RECENT_LIMIT = 5;

    /**
     * Tạo bảng trong workspace. user_id (người tạo) và workspace_id do server gán, không nhận từ input.
     *
     * @param  array{name: string, background?: ?string, visibility?: ?string}  $data  (đã validate)
     */
    public function create(Workspace $workspace, User $creator, array $data): Board
    {
        return Board::create([
            'workspace_id' => $workspace->getKey(),
            'user_id' => $creator->getKey(),
            'name' => $data['name'],
            'background' => $data['background'] ?? BoardBackground::default(),
            'visibility' => $data['visibility'] ?? BoardVisibility::default()->value,
        ]);
    }

    /**
     * @param  array{name: string, background: string, visibility: string}  $data  (đã validate)
     */
    public function update(Board $board, array $data): Board
    {
        $board->fill([
            'name' => $data['name'],
            'background' => $data['background'],
            'visibility' => $data['visibility'],
        ])->save();

        return $board;
    }

    /** Lưu trữ: ẩn khỏi Workspace::boards(), hiện ở Workspace::archivedBoards(). */
    public function archive(Board $board): Board
    {
        if (! $board->isArchived()) {
            $board->forceFill(['archived_at' => now()])->save();
        }

        return $board;
    }

    /** Khôi phục bảng đã lưu trữ về trạng thái hoạt động. */
    public function restore(Board $board): Board
    {
        if ($board->isArchived()) {
            $board->forceFill(['archived_at' => null])->save();
        }

        return $board;
    }

    /** Xóa mềm một bảng (SoftDeletes). */
    public function delete(Board $board): void
    {
        $board->delete();
    }

    /**
     * Xóa mềm TẤT CẢ bảng của một workspace (kể cả bảng đã lưu trữ).
     * WorkspaceService::delete() gọi hàm này thay vì tự Board::where()->delete().
     */
    public function deleteAllInWorkspace(Workspace $workspace): void
    {
        // Builder của model dùng SoftDeletes => đây là xóa mềm.
        Board::where('workspace_id', $workspace->getKey())->delete();
    }

    // ------------------------------------------------------------------
    // Trang kanban (bước 7)
    // ------------------------------------------------------------------

    /**
     * Nạp cột + thẻ ĐANG HOẠT ĐỘNG của bảng (theo position) cho trang kanban.
     *
     * Chỉ dùng activeTaskLists()/activeTasks(): taskLists()/tasks() gồm cả cột/thẻ đã lưu trữ.
     * Phải eager load đủ vì Model::preventLazyLoading đang bật ngoài production. Số truy vấn cố định
     * (cột, thẻ kèm đếm bình luận/tệp, nhãn, người được giao) dù bảng có bao nhiêu thẻ.
     */
    public function loadKanban(Board $board): Board
    {
        return $board->load([
            'activeTaskLists.activeTasks' => fn ($tasks) => $tasks
                ->with(['labels', 'assignees'])
                ->withCount(['comments', 'attachments']),
        ]);
    }

    /**
     * Dữ liệu truyền xuống Vue (BoardPage) qua <x-vue-mount>. Gọi SAU loadKanban().
     * Hình dạng cột/thẻ do KanbanPayload định nghĩa (chỉ trường hiển thị, không email/mô tả).
     *
     * @param  ?WorkspaceRole  $role     vai trò của người xem trong workspace; null = không phải thành viên
     *                                   (khách xem bảng public)
     * @param  bool            $canEdit  người xem có sửa được cột/thẻ không. Do BoardPolicy::editCards quyết
     *                                   định (một nguồn duy nhất, cùng quy tắc với TaskListPolicy...) nên
     *                                   Controller tính rồi truyền vào; Vue chỉ dùng để ẩn/hiện nút.
     * @return array<string, mixed>
     */
    public function kanbanProps(Board $board, ?WorkspaceRole $role, bool $canEdit): array
    {
        return [
            'board' => [
                'id' => $board->getKey(),
                'name' => $board->name,
                'archived' => $board->isArchived(),
            ],
            'role' => $role?->value,
            'canEdit' => $canEdit,
            'lists' => $board->activeTaskLists
                ->map(fn (TaskList $list) => KanbanPayload::list($list))
                ->values()
                ->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Gắn sao
    // ------------------------------------------------------------------

    /**
     * Bật/tắt gắn sao. Trả về true nếu SAU thao tác bảng đang được gắn sao.
     * (Quyền: BoardPolicy::star, kiểm tra ở controller.)
     */
    public function toggleStar(Board $board, User $user): bool
    {
        $result = $user->starredBoards()->toggle($board->getKey());

        return count($result['attached']) > 0;
    }

    public function isStarredBy(Board $board, User $user): bool
    {
        return $user->starredBoards()->whereKey($board->getKey())->exists();
    }

    /**
     * Trong số $boards, những id nào $user đã gắn sao (một truy vấn, tránh N+1 ở dashboard).
     *
     * @param  iterable<int, Board>  $boards
     * @return array<int, int>
     */
    public function starredIdsAmong(User $user, iterable $boards): array
    {
        $ids = collect($boards)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('board_stars')
            ->where('user_id', $user->getKey())
            ->whereIn('board_id', $ids)
            ->pluck('board_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Các bảng $user đã gắn sao VÀ hiện vẫn xem được (quyền có thể đã đổi sau khi gắn sao),
     * mới gắn sao trước.
     *
     * @return Collection<int, Board>
     */
    public function starredFor(User $user, int $limit = 8): Collection
    {
        return $user->starredBoards()
            ->accessibleBy($user)
            ->orderByDesc('board_stars.created_at')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------------
    // Gần đây (lưu trong session, không cần bảng riêng)
    // ------------------------------------------------------------------

    /** Ghi nhận $board vừa được mở (gọi ở BoardController@show). */
    public function recordRecent(Board $board): void
    {
        $ids = collect(session(self::RECENT_KEY, []))
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $board->getKey())
            ->prepend((int) $board->getKey())
            ->take(self::RECENT_LIMIT)
            ->values()
            ->all();

        session([self::RECENT_KEY => $ids]);
    }

    /**
     * Các bảng vừa mở trong phiên này mà $user hiện vẫn xem được, mới nhất trước.
     *
     * @return Collection<int, Board>
     */
    public function recentFor(User $user): Collection
    {
        $ids = array_map('intval', (array) session(self::RECENT_KEY, []));

        if ($ids === []) {
            return new Collection();
        }

        return Board::query()
            ->accessibleBy($user)
            ->whereIn('boards.id', $ids)
            ->get()
            ->sortBy(fn (Board $board) => array_search((int) $board->getKey(), $ids, true))
            ->values();
    }
}

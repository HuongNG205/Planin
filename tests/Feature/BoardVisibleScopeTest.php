<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsBoardFixtures;
use Tests\TestCase;

/**
 * Board::scopeVisibleTo / scopeAccessibleBy (lọc danh sách) phải cho kết quả GIỐNG BoardPolicy::view
 * (kiểm tra từng bảng) với mọi người dùng.
 */
class BoardVisibleScopeTest extends TestCase
{
    use BuildsBoardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBoardFixtures();
    }

    private function seedBoards(): void
    {
        foreach (['private', 'workspace', 'public'] as $visibility) {
            $this->makeBoard($visibility, $this->admin, ['name' => "Admin {$visibility}"]);
            $this->makeBoard($visibility, $this->member, ['name' => "Member {$visibility}"]);
        }

        // Bảng đã lưu trữ không có trong Workspace::boards() / scopeAccessibleBy.
        $this->makeBoard('workspace', $this->admin, ['name' => 'Cũ', 'archived_at' => now()]);
    }

    /** @return array<int, User> */
    private function everyone(): array
    {
        return [$this->admin, $this->member, $this->member2, $this->viewer, $this->outsider];
    }

    public function test_visible_to_khop_voi_policy_view(): void
    {
        $this->seedBoards();

        foreach ($this->everyone() as $user) {
            $workspace = Workspace::findOrFail($this->workspace->id);
            $role = $workspace->roleOf($user);

            $fromScope = $workspace->boards()->visibleTo($user, $role)->pluck('boards.id')->sort()->values()->all();

            $fromPolicy = Board::where('workspace_id', $workspace->id)
                ->whereNull('archived_at')
                ->with('workspace')
                ->get()
                ->filter(fn (Board $board) => Gate::forUser($user)->allows('view', $board))
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($fromPolicy, $fromScope, "Danh sách bảng của {$user->name} lệch với BoardPolicy::view");
        }
    }

    public function test_accessible_by_khop_voi_policy_view_va_loai_bang_luu_tru(): void
    {
        $this->seedBoards();

        // Một workspace khác có bảng công khai và bảng riêng của người lạ.
        $other = $this->makeWorkspace($this->outsider, 'private');
        $this->makeBoard('public', $this->outsider, ['name' => 'Ngoài public'], $other);
        $this->makeBoard('private', $this->outsider, ['name' => 'Ngoài private'], $other);

        foreach ($this->everyone() as $user) {
            $fromScope = Board::query()->accessibleBy($user)->pluck('boards.id')->sort()->values()->all();

            $fromPolicy = Board::query()
                ->whereNull('archived_at')
                ->with('workspace')
                ->get()
                ->filter(fn (Board $board) => Gate::forUser($user)->allows('view', $board))
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($fromPolicy, $fromScope, "accessibleBy của {$user->name} lệch với BoardPolicy::view");
        }
    }

    public function test_accessible_by_bo_qua_bang_cua_workspace_da_xoa_mem(): void
    {
        $board = $this->makeBoard('public', $this->admin);

        $this->assertContains($board->id, Board::query()->accessibleBy($this->outsider)->pluck('boards.id')->all());

        $this->workspace->delete();

        $this->assertNotContains($board->id, Board::query()->accessibleBy($this->outsider)->pluck('boards.id')->all());
    }
}

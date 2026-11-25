<?php

namespace Tests\Concerns;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

/**
 * Dữ liệu dựng sẵn cho test bước 6 (bảng): một workspace với đủ các vai trò.
 *
 *   $this->admin    : chủ sở hữu, role admin
 *   $this->member   : role member (cũng là người tạo bảng 'private' trong test ma trận)
 *   $this->member2  : role member (không phải người tạo)
 *   $this->viewer   : role viewer
 *   $this->outsider : không phải thành viên
 */
trait BuildsBoardFixtures
{
    protected User $admin;

    protected User $member;

    protected User $member2;

    protected User $viewer;

    protected User $outsider;

    protected Workspace $workspace;

    protected function setUpBoardFixtures(): void
    {
        // Không cần build Vite khi test render Blade.
        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->member2 = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->workspace = $this->makeWorkspace($this->admin, 'private', [
            [$this->member, 'member'],
            [$this->member2, 'member'],
            [$this->viewer, 'viewer'],
        ]);
    }

    /**
     * @param  array<int, array{0: User, 1: string}>  $members  [[user, role], ...]
     */
    protected function makeWorkspace(User $owner, string $visibility = 'private', array $members = []): Workspace
    {
        $workspace = Workspace::factory()->create([
            'user_id' => $owner->id,
            'visibility' => $visibility,
        ]);

        $workspace->members()->attach($owner->id, ['role' => 'admin']);

        foreach ($members as [$user, $role]) {
            $workspace->members()->attach($user->id, ['role' => $role]);
        }

        return $workspace;
    }

    protected function makeBoard(string $visibility = 'workspace', ?User $creator = null, array $attributes = [], ?Workspace $workspace = null): Board
    {
        $workspace ??= $this->workspace;

        return Board::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'user_id' => ($creator ?? $this->admin)->id,
            'visibility' => $visibility,
            'archived_at' => null,
        ], $attributes));
    }

    /** Tải lại workspace/board từ DB (tránh cache vai trò trong instance). */
    protected function fresh(Board $board): Board
    {
        return Board::withTrashed()->findOrFail($board->id);
    }
}

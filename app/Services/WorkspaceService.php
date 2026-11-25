<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceVisibility;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ WORKSPACE. Service không kiểm tra quyền (đã có Policy/FormRequest);
 * chỉ lo dữ liệu và các thao tác phải đi cùng nhau trong một transaction.
 */
class WorkspaceService
{
    /**
     * Các workspace mà $user là THÀNH VIÊN (kể cả được mời), mới nhất trước.
     * Truy vấn qua workspace_members chứ không theo workspaces.user_id.
     *
     * @return Collection<int, Workspace>  mỗi phần tử có ->pivot->role
     */
    public function forUser(User $user): Collection
    {
        return $user->workspaces()
            ->orderByDesc('workspaces.created_at')
            ->orderByDesc('workspaces.id')
            ->get();
    }

    /**
     * Tạo workspace và thêm chủ sở hữu vào workspace_members với role admin.
     * Hai việc này luôn đi cùng nhau, nếu một việc lỗi thì không việc nào được lưu.
     *
     * @param  array{name: string, description?: ?string, visibility?: ?string}  $data  (đã validate)
     */
    public function create(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            // user_id do server gán, không nhận từ input.
            $workspace = Workspace::create([
                'user_id' => $owner->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => config('planin.workspace_colors')[0] ?? '#AA6ADA',
                'visibility' => $data['visibility'] ?? WorkspaceVisibility::default()->value,
            ]);

            $workspace->members()->attach($owner->getKey(), [
                'role' => WorkspaceRole::Admin->value,
            ]);

            return $workspace;
        });
    }

    /**
     * @param  array{name: string, description?: ?string, visibility: string}  $data  (đã validate)
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
        ])->save();

        return $workspace;
    }

    /**
     * Xóa mềm workspace cùng các bảng của nó, để bảng không còn truy cập được
     * (bảng chỉ đi qua workspace).
     */
    public function delete(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace) {
            // Builder của model dùng SoftDeletes => đây là xóa mềm, kể cả bảng đã lưu trữ.
            Board::where('workspace_id', $workspace->getKey())->delete();

            $workspace->delete();
        });
    }
}

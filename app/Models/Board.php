<?php

namespace App\Models;

use App\Enums\BoardVisibility;
use App\Enums\WorkspaceRole;
use App\Support\PlaninEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'boards';
    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'background',
        'visibility',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Lọc các bảng mà $user được XEM trong MỘT workspace, dựa vào vai trò của họ ở workspace đó
     * ($role = Workspace::roleOf($user), null nếu không phải thành viên). Chỉ một truy vấn,
     * không gọi Policy từng bảng. Phải khớp với BoardPolicy::view() (có test đối chiếu):
     *
     *   admin           : mọi bảng
     *   member / viewer : bảng 'workspace', bảng 'public', và bảng 'private' do chính mình tạo
     *   không phải TV   : chỉ bảng 'public'
     *
     * Scope KHÔNG lọc archived_at (Workspace::boards() đã lọc; trang lưu trữ chỉ dành cho admin).
     */
    public function scopeVisibleTo(Builder $query, User $user, ?WorkspaceRole $role): Builder
    {
        if ($role?->canManage()) {
            return $query;
        }

        if ($role === null) {
            return $query->where('boards.visibility', BoardVisibility::Public->value);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereIn('boards.visibility', [BoardVisibility::Workspace->value, BoardVisibility::Public->value])
                ->orWhere(function (Builder $own) use ($user) {
                    $own->where('boards.visibility', BoardVisibility::Private->value)
                        ->where('boards.user_id', $user->getKey());
                });
        });
    }

    /**
     * Lọc các bảng đang hoạt động mà $user xem được Ở BẤT KỲ workspace nào (dùng cho mục
     * "Đã gắn sao" / "Gần đây" ở sidebar). Cùng quy tắc với scopeVisibleTo nhưng tự tra
     * vai trò trong workspace_members bằng subquery, và loại bảng của workspace đã xóa mềm.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        $userId = $user->getKey();

        return $query
            ->whereNull('boards.archived_at')
            ->whereHas('workspace')
            ->where(function (Builder $q) use ($userId) {
                $q->where('boards.visibility', BoardVisibility::Public->value)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->selectRaw('1')
                            ->from('workspace_members as wm')
                            ->whereColumn('wm.workspace_id', 'boards.workspace_id')
                            ->where('wm.user_id', $userId)
                            ->where(function ($m) use ($userId) {
                                $m->where('wm.role', WorkspaceRole::Admin->value)
                                    ->orWhere('boards.visibility', BoardVisibility::Workspace->value)
                                    ->orWhere(function ($p) use ($userId) {
                                        $p->where('boards.visibility', BoardVisibility::Private->value)
                                            ->where('boards.user_id', $userId);
                                    });
                            });
                    });
            });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    protected function visibilityLabel(): Attribute
    {
        return Attribute::get(fn () => PlaninEnum::label('board_visibility', $this->visibility));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Người tạo bảng. boards.user_id có thể NULL (user bị xóa cứng) nên khi hiển thị
     * luôn dùng $board->user?->name.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** TẤT CẢ cột (kể cả đã lưu trữ), theo thứ tự. Khi hiển thị bảng dùng activeTaskLists(). */
    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    /** Các cột đang hoạt động (chưa lưu trữ), theo thứ tự: dùng cho trang kanban (bước 7). */
    public function activeTaskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->whereNull('archived_at')->orderBy('position');
    }

    /** Các cột đã lưu trữ (cho trang lưu trữ, bước 13). */
    public function archivedTaskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->whereNotNull('archived_at')->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** Những người đã gắn sao bảng này. */
    public function stars(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_stars')->withTimestamps();
    }
}

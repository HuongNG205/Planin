<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Support\PlaninEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
        'visibility',
    ];

    /** Cache vai trò theo user trong vòng đời của instance (tránh truy vấn lặp khi gọi Policy nhiều lần). */
    private array $roleCache = [];

    protected function visibilityLabel(): Attribute
    {
        return Attribute::get(fn () => PlaninEnum::label('workspace_visibility', $this->visibility));
    }

    /** Chủ sở hữu = người tạo workspace (workspaces.user_id), không phải một role. */
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }

    /**
     * Vai trò của $user trong workspace này (workspace_members.role);
     * null nếu người đó không phải thành viên.
     */
    public function roleOf(User $user): ?WorkspaceRole
    {
        $key = $user->getKey();

        if (! array_key_exists($key, $this->roleCache)) {
            $role = $this->members()->wherePivot('user_id', $key)->first()?->pivot?->role;
            $this->roleCache[$key] = $role !== null ? WorkspaceRole::tryFrom($role) : null;
        }

        return $this->roleCache[$key];
    }

    /** Gọi sau khi đổi/xóa thành viên trong cùng request để roleOf() không trả dữ liệu cũ. */
    public function forgetRoleCache(): void
    {
        $this->roleCache = [];
    }

    public function isMember(User $user): bool
    {
        return $this->roleOf($user) !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Các bảng đang hoạt động (chưa lưu trữ), mới nhất trước. */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class)->whereNull('archived_at')->latest();
    }

    public function archivedBoards(): HasMany
    {
        return $this->hasMany(Board::class)->whereNotNull('archived_at')->latest('archived_at');
    }

    /** Nhãn thuộc các bảng của workspace (nhãn lưu theo bảng). */
    public function labels(): HasManyThrough
    {
        return $this->hasManyThrough(Label::class, Board::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}

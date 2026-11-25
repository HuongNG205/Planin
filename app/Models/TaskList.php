<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'task_lists';
    protected $fillable = [
        'board_id',
        'name',
        'position',
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

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** TẤT CẢ thẻ của cột (kể cả đã lưu trữ), theo thứ tự. Khi hiển thị cột dùng activeTasks(). */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    /** Các thẻ đang hoạt động (chưa lưu trữ), theo thứ tự: dùng cho trang kanban (bước 7). */
    public function activeTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('archived_at')->orderBy('position');
    }

    /** Các thẻ đã lưu trữ của cột (cho trang lưu trữ, bước 13). */
    public function archivedTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNotNull('archived_at')->orderBy('position');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}

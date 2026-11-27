<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kéo thả (bước 10): PATCH /tasks/{task}/move và PATCH /lists/{list}/move.
 * Kiểm tra thứ tự sau khi kéo, quyền theo vai trò, ràng buộc cùng bảng / chưa lưu trữ và việc position được đánh số lại.
 */
class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Board $board;

    /** @var array<int, TaskList> */
    private array $lists = [];

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $this->workspace->members()->attach($owner->id, ['role' => 'admin']);
        $this->board = Board::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $owner->id,
            'visibility' => 'workspace',
        ]);

        foreach ([0, 1, 2] as $i) {
            $this->lists[$i] = TaskList::factory()->create(['board_id' => $this->board->id, 'position' => $i]);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->workspace->members()->attach($user->id, ['role' => $role]);

        return $user;
    }

    private function tasks(TaskList $list, int $count): array
    {
        $tasks = [];
        foreach (range(0, $count - 1) as $i) {
            $tasks[] = Task::factory()->create(['task_list_id' => $list->id, 'position' => $i]);
        }

        return $tasks;
    }

    /** id các thẻ đang hoạt động của cột, theo thứ tự hiển thị. */
    private function order(TaskList $list): array
    {
        return $list->activeTasks()->pluck('id')->all();
    }

    public function test_reorder_within_list(): void
    {
        [$a, $b, $c] = $this->tasks($this->lists[0], 3);

        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[0]->id, 'position' => 2])
            ->assertOk();

        $this->assertSame([$b->id, $c->id, $a->id], $this->order($this->lists[0]));
        $this->assertSame([0, 1, 2], $this->lists[0]->activeTasks()->pluck('position')->all());
    }

    public function test_move_to_another_list_renumbers_both_lists(): void
    {
        [$a, $b] = $this->tasks($this->lists[0], 2);
        [$x, $y] = $this->tasks($this->lists[1], 2);

        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => 1])
            ->assertOk()
            ->assertJsonPath('list_id', $this->lists[1]->id);

        $this->assertSame([$b->id], $this->order($this->lists[0]));
        $this->assertSame([$x->id, $a->id, $y->id], $this->order($this->lists[1]));
        $this->assertSame([0, 1, 2], $this->lists[1]->activeTasks()->pluck('position')->all());
        $this->assertSame([0], $this->lists[0]->activeTasks()->pluck('position')->all());
    }

    public function test_move_into_empty_list_and_position_beyond_end(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);
        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[2]->id, 'position' => 50])
            ->assertOk();

        $this->assertSame([$a->id], $this->order($this->lists[2]));
    }

    public function test_archived_tasks_keep_relative_order_and_do_not_count_for_index(): void
    {
        [$a, $b] = $this->tasks($this->lists[0], 2);
        $archived = Task::factory()->archived()->create(['task_list_id' => $this->lists[0]->id, 'position' => 1]);
        $a->update(['position' => 0]);
        $archived->update(['position' => 1]);
        $b->update(['position' => 2]);

        // Hiển thị chỉ có [a, b]; kéo a xuống vị trí 1 => [b, a]
        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[0]->id, 'position' => 1])
            ->assertOk();

        $this->assertSame([$b->id, $a->id], $this->order($this->lists[0]));
        $positions = Task::where('task_list_id', $this->lists[0]->id)->pluck('position')->all();
        $this->assertSame(count($positions), count(array_unique($positions)), 'position không được trùng nhau');
    }

    public function test_viewer_cannot_move(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);

        $this->actingAs($this->userWithRole('viewer'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => 0])
            ->assertForbidden();

        $this->assertSame($this->lists[0]->id, $a->fresh()->task_list_id);
    }

    public function test_outsider_cannot_move(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => 0])
            ->assertForbidden();
    }

    public function test_cannot_move_to_list_of_another_board(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);
        $otherBoard = Board::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->workspace->user_id,
            'visibility' => 'workspace',
        ]);
        $foreign = TaskList::factory()->create(['board_id' => $otherBoard->id, 'position' => 0]);

        $this->actingAs($this->userWithRole('admin'))
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $foreign->id, 'position' => 0])
            ->assertStatus(422);

        $this->assertSame($this->lists[0]->id, $a->fresh()->task_list_id);
    }

    public function test_cannot_move_to_list_of_board_user_cannot_edit(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);
        $admin = $this->workspace->owner;
        $member = $this->userWithRole('member');
        // Bảng private của admin: member không xem/sửa được.
        $private = Board::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'visibility' => 'private',
        ]);
        $foreign = TaskList::factory()->create(['board_id' => $private->id, 'position' => 0]);

        $this->actingAs($member)
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $foreign->id, 'position' => 0])
            ->assertForbidden();
    }

    public function test_cannot_move_to_archived_list_or_on_archived_board(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);
        $member = $this->userWithRole('member');

        $this->lists[1]->forceFill(['archived_at' => now()])->save();
        $this->actingAs($member)
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => 0])
            ->assertForbidden();

        $this->board->forceFill(['archived_at' => now()])->save();
        $this->actingAs($this->workspace->owner)
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[2]->id, 'position' => 0])
            ->assertForbidden();
    }

    public function test_move_validates_input(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);
        $member = $this->userWithRole('member');

        $this->actingAs($member)
            ->patchJson(route('tasks.move', $a), ['to_list_id' => 999999, 'position' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_list_id');

        $this->actingAs($member)
            ->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('position');
    }

    public function test_reorder_lists_including_archived_gap(): void
    {
        $this->lists[1]->forceFill(['archived_at' => now()])->save(); // hiển thị: [0, 2]

        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('lists.move', $this->lists[0]), ['position' => 1])
            ->assertOk();

        $this->assertSame(
            [$this->lists[2]->id, $this->lists[0]->id],
            $this->board->activeTaskLists()->pluck('id')->all(),
        );
        $positions = TaskList::where('board_id', $this->board->id)->pluck('position')->all();
        $this->assertSame(count($positions), count(array_unique($positions)), 'position cột không được trùng nhau');
    }

    public function test_viewer_cannot_reorder_lists_and_archived_list_cannot_move(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->patchJson(route('lists.move', $this->lists[0]), ['position' => 1])
            ->assertForbidden();

        $this->lists[0]->forceFill(['archived_at' => now()])->save();
        $this->actingAs($this->userWithRole('member'))
            ->patchJson(route('lists.move', $this->lists[0]), ['position' => 1])
            ->assertForbidden();
    }

    public function test_guest_gets_401_on_background_request(): void
    {
        [$a] = $this->tasks($this->lists[0], 1);

        $this->patchJson(route('tasks.move', $a), ['to_list_id' => $this->lists[1]->id, 'position' => 0])
            ->assertUnauthorized();
    }
}

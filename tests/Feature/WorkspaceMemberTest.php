<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceMemberTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id, 'visibility' => 'private']);
        $this->workspace->members()->attach($this->owner->id, ['role' => 'admin']);
    }

    private function addMember(string $role): User
    {
        $user = User::factory()->create();
        $this->workspace->members()->attach($user->id, ['role' => $role]);

        return $user;
    }

    private function role(User $user): ?string
    {
        return DB::table('workspace_members')
            ->where('workspace_id', $this->workspace->id)->where('user_id', $user->id)->value('role');
    }

    public function test_admin_can_invite_by_email_and_default_role_is_member(): void
    {
        $admin = $this->addMember('admin');
        $invitee = User::factory()->create(['email' => 'moi@example.com']);

        $this->actingAs($admin)
            ->post(route('workspaces.members.store', $this->workspace), ['email' => '  MOI@example.com '])
            ->assertRedirect(route('workspaces.show', $this->workspace));

        $this->assertSame('member', $this->role($invitee));
    }

    public function test_invite_validation_errors(): void
    {
        $existing = $this->addMember('member');

        $this->actingAs($this->owner)
            ->post(route('workspaces.members.store', $this->workspace), ['email' => 'khong-ton-tai@example.com'])
            ->assertSessionHasErrors('email', null, 'inviteMember');

        $this->actingAs($this->owner)
            ->post(route('workspaces.members.store', $this->workspace), ['email' => $existing->email])
            ->assertSessionHasErrors('email', null, 'inviteMember');

        $this->actingAs($this->owner)
            ->post(route('workspaces.members.store', $this->workspace), ['email' => 'a@b.co', 'role' => 'owner'])
            ->assertSessionHasErrors('role', null, 'inviteMember');
    }

    public function test_member_viewer_and_stranger_cannot_invite(): void
    {
        $target = User::factory()->create();
        $payload = ['email' => $target->email];

        foreach (['member', 'viewer'] as $role) {
            $this->actingAs($this->addMember($role))
                ->post(route('workspaces.members.store', $this->workspace), $payload)->assertForbidden();
        }

        // Người ngoài bị middleware chặn, kể cả khi workspace công khai.
        $this->workspace->update(['visibility' => 'public']);
        $this->actingAs(User::factory()->create())
            ->post(route('workspaces.members.store', $this->workspace), $payload)->assertForbidden();

        $this->assertNull($this->role($target));
    }

    public function test_admin_can_change_role_but_not_owner_role(): void
    {
        $admin = $this->addMember('admin');
        $member = $this->addMember('member');

        $this->actingAs($admin)
            ->put(route('workspaces.members.update', [$this->workspace, $member]), ['role' => 'viewer'])
            ->assertRedirect();
        $this->assertSame('viewer', $this->role($member));

        $this->actingAs($admin)
            ->put(route('workspaces.members.update', [$this->workspace, $this->owner]), ['role' => 'viewer'])
            ->assertForbidden();
        $this->assertSame('admin', $this->role($this->owner));
    }

    public function test_non_admin_cannot_change_roles(): void
    {
        $member = $this->addMember('member');
        $other = $this->addMember('viewer');

        $this->actingAs($member)
            ->put(route('workspaces.members.update', [$this->workspace, $other]), ['role' => 'admin'])
            ->assertForbidden();
        $this->assertSame('viewer', $this->role($other));
    }

    public function test_admin_can_remove_member_and_assignments_and_stars_are_cleaned(): void
    {
        $member = $this->addMember('member');
        $board = Board::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
        $list = TaskList::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['task_list_id' => $list->id]);
        $task->assignees()->attach($member->id);
        $board->stars()->attach($member->id);

        $this->actingAs($this->owner)
            ->delete(route('workspaces.members.destroy', [$this->workspace, $member]))
            ->assertRedirect();

        $this->assertNull($this->role($member));
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('board_stars', ['board_id' => $board->id, 'user_id' => $member->id]);
    }

    public function test_owner_cannot_be_removed(): void
    {
        $admin = $this->addMember('admin');

        $this->actingAs($admin)
            ->delete(route('workspaces.members.destroy', [$this->workspace, $this->owner]))
            ->assertForbidden();
        $this->assertSame('admin', $this->role($this->owner));
    }

    public function test_removed_member_can_be_invited_again(): void
    {
        $member = $this->addMember('member');

        $this->actingAs($this->owner)->delete(route('workspaces.members.destroy', [$this->workspace, $member]));
        $this->actingAs($this->owner)
            ->post(route('workspaces.members.store', $this->workspace), ['email' => $member->email, 'role' => 'viewer'])
            ->assertRedirect();

        $this->assertSame('viewer', $this->role($member));
    }

    public function test_viewer_sees_member_list_but_no_management_controls(): void
    {
        $viewer = $this->addMember('viewer');

        $this->actingAs($viewer)->get(route('workspaces.show', $this->workspace))
            ->assertOk()
            ->assertSee($this->owner->email)
            ->assertDontSee(route('workspaces.members.store', $this->workspace));
    }
}

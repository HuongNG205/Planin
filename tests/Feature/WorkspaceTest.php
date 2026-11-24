<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithMember(string $role): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'admin']);

        $user = User::factory()->create();
        $workspace->members()->attach($user->id, ['role' => $role]);

        return [$owner, $user, $workspace];
    }

    public function test_store_adds_owner_as_admin_and_defaults_to_private(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workspaces.store'), ['name' => 'Nhóm A', 'type' => 'hr'])
            ->assertRedirect();

        $workspace = Workspace::where('name', 'Nhóm A')->firstOrFail();

        $this->assertSame('private', $workspace->visibility);
        $this->assertSame($user->id, $workspace->user_id);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_store_rejects_invalid_visibility(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('workspaces.store'), ['name' => 'X', 'visibility' => 'hacked'])
            ->assertSessionHasErrors('visibility', null, 'createWorkspace');
    }

    public function test_invited_member_sees_workspace_but_stranger_gets_403(): void
    {
        [, $member, $workspace] = $this->workspaceWithMember('viewer');
        $stranger = User::factory()->create();

        $this->actingAs($member)->get(route('workspaces.index'))
            ->assertOk()->assertSee($workspace->name);

        $this->actingAs($member)->get(route('workspaces.show', $workspace))->assertOk();
        $this->actingAs($stranger)->get(route('workspaces.show', $workspace))->assertForbidden();
    }

    public function test_only_owner_can_update_and_delete(): void
    {
        [$owner, $admin, $workspace] = $this->workspaceWithMember('admin');
        $payload = ['name' => 'Tên mới', 'visibility' => 'public'];

        $this->actingAs($admin)->put(route('workspaces.update', $workspace), $payload)->assertForbidden();
        $this->actingAs($admin)->delete(route('workspaces.destroy', $workspace))->assertForbidden();

        $this->actingAs($owner)->put(route('workspaces.update', $workspace), $payload)->assertRedirect();
        $this->assertSame('Tên mới', $workspace->fresh()->name);

        $this->actingAs($owner)->delete(route('workspaces.destroy', $workspace))->assertRedirect();
        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    }

    public function test_member_can_leave_but_owner_cannot(): void
    {
        [$owner, $member, $workspace] = $this->workspaceWithMember('member');

        $this->actingAs($owner)->post(route('workspaces.leave', $workspace))->assertForbidden();

        $this->actingAs($member)->post(route('workspaces.leave', $workspace))->assertRedirect();
        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_only_workspace_admin_can_create_board(): void
    {
        [$owner, $member, $workspace] = $this->workspaceWithMember('member');
        $payload = ['workspace_id' => $workspace->id, 'name' => 'Bảng 1'];

        $this->actingAs($member)->post(route('boards.store'), $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('boards.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('boards', ['workspace_id' => $workspace->id, 'name' => 'Bảng 1']);
    }
}

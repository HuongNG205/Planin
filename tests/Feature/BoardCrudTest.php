<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Support\BoardBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsBoardFixtures;
use Tests\TestCase;

/**
 * Bước 6 qua HTTP: tạo/sửa/lưu trữ/khôi phục/xóa bảng, trang bảng, danh sách theo quyền xem,
 * IDOR, gắn sao. Nền sai phải bị từ chối ở cả store và update.
 */
class BoardCrudTest extends TestCase
{
    use BuildsBoardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBoardFixtures();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bảng thử nghiệm',
            'background' => BoardBackground::default(),
            'visibility' => 'workspace',
        ], $overrides);
    }

    /** Người không phải admin của workspace. */
    private function nonAdmins(): array
    {
        return [$this->member, $this->viewer, $this->outsider];
    }

    /** @return array<string, array{0: string}> */
    public static function badBackgrounds(): array
    {
        return [
            'chèn CSS' => ['red; background-image:url(http://evil.test/x.png)'],
            'javascript' => ['javascript:alert(1)'],
            'url' => ['url(http://evil.test/x.png)'],
            'hex thiếu' => ['#12'],
            'hex sai ký tự' => ['#GGGGGG'],
            'gradient ngoài preset' => ['linear-gradient(135deg, #000000, #ffffff)'],
        ];
    }

    // ------------------------------------------------------------------
    // Tạo bảng
    // ------------------------------------------------------------------

    public function test_admin_tao_bang(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), $this->payload(['name' => '  Sprint 1  ', 'visibility' => 'private']));

        $response->assertRedirect(route('workspaces.show', $this->workspace));

        $this->assertDatabaseHas('boards', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id, // người tạo do server gán
            'name' => 'Sprint 1',          // đã trim
            'visibility' => 'private',
        ]);
    }

    public function test_tao_bang_khong_gui_nen_va_quyen_xem_thi_dung_mac_dinh(): void
    {
        $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), ['name' => 'Mặc định'])
            ->assertRedirect();

        $this->assertDatabaseHas('boards', [
            'name' => 'Mặc định',
            'background' => BoardBackground::default(),
            'visibility' => 'workspace',
        ]);
    }

    public function test_khong_phai_admin_khong_tao_duoc_bang(): void
    {
        foreach ($this->nonAdmins() as $user) {
            $this->actingAs($user)
                ->post(route('boards.store', $this->workspace), $this->payload(['name' => 'Lén tạo']))
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('boards', ['name' => 'Lén tạo']);
    }

    public function test_khach_chua_dang_nhap_bi_chuyen_ve_trang_dang_nhap(): void
    {
        $board = $this->makeBoard('public');

        $this->post(route('boards.store', $this->workspace), $this->payload())->assertRedirect(route('login'));
        $this->get(route('boards.show', $board))->assertRedirect(route('login'));
    }

    #[DataProvider('badBackgrounds')]
    public function test_tao_bang_tu_choi_nen_sai(string $background): void
    {
        $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), $this->payload(['name' => 'Nền sai', 'background' => $background]))
            ->assertSessionHasErrors('background', errorBag: 'createBoard');

        $this->assertDatabaseMissing('boards', ['name' => 'Nền sai']);
    }

    public function test_tao_bang_chap_nhan_preset_va_ma_mau_hex(): void
    {
        $presets = BoardBackground::values();
        $valid = [$presets[0], '#abc', '#123456'];

        foreach ($valid as $i => $background) {
            $this->actingAs($this->admin)
                ->post(route('boards.store', $this->workspace), $this->payload(['name' => "Nền hợp lệ {$i}", 'background' => $background]))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('boards', ['name' => "Nền hợp lệ {$i}", 'background' => $background]);
        }
    }

    public function test_tao_bang_tu_choi_ten_va_quyen_xem_sai(): void
    {
        $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), $this->payload(['name' => '   ']))
            ->assertSessionHasErrors('name', errorBag: 'createBoard');

        $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), $this->payload(['name' => str_repeat('a', 151)]))
            ->assertSessionHasErrors('name', errorBag: 'createBoard');

        $this->actingAs($this->admin)
            ->post(route('boards.store', $this->workspace), $this->payload(['name' => 'Quyền sai', 'visibility' => 'everyone']))
            ->assertSessionHasErrors('visibility', errorBag: 'createBoard');

        $this->assertDatabaseMissing('boards', ['name' => 'Quyền sai']);
    }

    // ------------------------------------------------------------------
    // Sửa bảng
    // ------------------------------------------------------------------

    public function test_admin_sua_bang(): void
    {
        $board = $this->makeBoard('workspace');

        $this->actingAs($this->admin)
            ->put(route('boards.update', $board), $this->payload(['name' => 'Tên mới', 'background' => '#123456', 'visibility' => 'public']))
            ->assertRedirect(route('boards.show', $board));

        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'name' => 'Tên mới',
            'background' => '#123456',
            'visibility' => 'public',
        ]);
    }

    #[DataProvider('badBackgrounds')]
    public function test_sua_bang_tu_choi_nen_sai(string $background): void
    {
        $board = $this->makeBoard('workspace', null, ['name' => 'Giữ nguyên', 'background' => '#0079BF']);

        $this->actingAs($this->admin)
            ->put(route('boards.update', $board), $this->payload(['name' => 'Đã đổi', 'background' => $background]))
            ->assertSessionHasErrors('background', errorBag: 'updateBoard');

        $this->assertDatabaseHas('boards', ['id' => $board->id, 'name' => 'Giữ nguyên', 'background' => '#0079BF']);
    }

    public function test_khong_phai_admin_khong_sua_duoc_bang(): void
    {
        $board = $this->makeBoard('public', null, ['name' => 'Không được sửa']);

        foreach ($this->nonAdmins() as $user) {
            $this->actingAs($user)
                ->put(route('boards.update', $board), $this->payload(['name' => 'Bị sửa']))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('boards', ['id' => $board->id, 'name' => 'Không được sửa']);
    }

    public function test_khong_sua_duoc_bang_da_luu_tru(): void
    {
        $board = $this->makeBoard('workspace', null, ['name' => 'Đã lưu trữ', 'archived_at' => now()]);

        $this->actingAs($this->admin)
            ->put(route('boards.update', $board), $this->payload(['name' => 'Bị sửa']))
            ->assertForbidden();

        $this->assertDatabaseHas('boards', ['id' => $board->id, 'name' => 'Đã lưu trữ']);
    }

    public function test_admin_workspace_khac_khong_thao_tac_duoc_bang_cua_workspace_nay(): void
    {
        // IDOR: admin của workspace B đổi id trên URL sang bảng của workspace A.
        $otherAdmin = User::factory()->create();
        $this->makeWorkspace($otherAdmin);
        $board = $this->makeBoard('public', null, ['name' => 'Của workspace A']);

        $this->actingAs($otherAdmin);

        $this->put(route('boards.update', $board), $this->payload(['name' => 'Chiếm quyền']))->assertForbidden();
        $this->post(route('boards.archive', $board))->assertForbidden();
        $this->delete(route('boards.destroy', $board))->assertForbidden();
        $this->post(route('boards.store', $this->workspace), $this->payload(['name' => 'Chiếm quyền']))->assertForbidden();

        $this->assertDatabaseHas('boards', ['id' => $board->id, 'name' => 'Của workspace A', 'deleted_at' => null, 'archived_at' => null]);
        $this->assertDatabaseMissing('boards', ['name' => 'Chiếm quyền']);
    }

    // ------------------------------------------------------------------
    // Trang bảng (thẻ bảng dẫn tới /boards/{id})
    // ------------------------------------------------------------------

    public function test_trang_bang_theo_visibility(): void
    {
        $private = $this->makeBoard('private', $this->member, ['name' => 'Bảng riêng']);
        $workspaceBoard = $this->makeBoard('workspace', $this->admin, ['name' => 'Bảng chung']);
        $public = $this->makeBoard('public', $this->admin, ['name' => 'Bảng công khai']);

        // private: admin + người tạo
        $this->actingAs($this->admin)->get(route('boards.show', $private))->assertOk()->assertSee('Bảng riêng');
        $this->actingAs($this->member)->get(route('boards.show', $private))->assertOk();
        $this->actingAs($this->member2)->get(route('boards.show', $private))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('boards.show', $private))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('boards.show', $private))->assertForbidden();

        // workspace: mọi thành viên
        $this->actingAs($this->viewer)->get(route('boards.show', $workspaceBoard))->assertOk();
        $this->actingAs($this->outsider)->get(route('boards.show', $workspaceBoard))->assertForbidden();

        // public: mọi người đã đăng nhập
        $this->actingAs($this->outsider)->get(route('boards.show', $public))->assertOk();
    }

    public function test_nut_sua_chi_hien_cho_admin_va_viewer_khong_thay(): void
    {
        $board = $this->makeBoard('workspace');

        $this->actingAs($this->admin)->get(route('boards.show', $board))
            ->assertOk()->assertSee('editBoardModal', false);

        $this->actingAs($this->viewer)->get(route('boards.show', $board))
            ->assertOk()->assertDontSee('editBoardModal', false);
    }

    public function test_trang_bang_khong_ton_tai_hoac_da_xoa_tra_ve_404(): void
    {
        $board = $this->makeBoard('public');
        $this->actingAs($this->admin);

        $this->get('/boards/abc')->assertNotFound();
        $this->get('/boards/999999')->assertNotFound();

        $board->delete();
        $this->get(route('boards.show', $board->id))->assertNotFound();
    }

    public function test_bang_da_luu_tru_chi_admin_vao_duoc(): void
    {
        $board = $this->makeBoard('public', null, ['archived_at' => now()]);

        $this->actingAs($this->admin)->get(route('boards.show', $board))->assertOk();
        $this->actingAs($this->member)->get(route('boards.show', $board))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('boards.show', $board))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Danh sách bảng trên dashboard (lọc theo quyền xem)
    // ------------------------------------------------------------------

    private function link(Board $board): string
    {
        return 'href="'.route('boards.show', $board).'"';
    }

    public function test_dashboard_loc_bang_theo_quyen_xem(): void
    {
        $adminPrivate = $this->makeBoard('private', $this->admin, ['name' => 'Riêng của admin']);
        $memberPrivate = $this->makeBoard('private', $this->member, ['name' => 'Riêng của member']);
        $shared = $this->makeBoard('workspace', $this->admin, ['name' => 'Chung']);
        $public = $this->makeBoard('public', $this->admin, ['name' => 'Công khai']);
        $archived = $this->makeBoard('workspace', $this->admin, ['name' => 'Đã cất', 'archived_at' => now()]);

        // admin: mọi bảng đang hoạt động, KHÔNG có bảng đã lưu trữ; thẻ bảng là <a>
        $admin = $this->actingAs($this->admin)->get(route('workspaces.show', $this->workspace))->assertOk();
        foreach ([$adminPrivate, $memberPrivate, $shared, $public] as $board) {
            $admin->assertSee($this->link($board), false);
        }
        $admin->assertDontSee($this->link($archived), false);

        // member (người tạo bảng riêng): thấy bảng riêng của mình, không thấy bảng riêng của admin
        $member = $this->actingAs($this->member)->get(route('workspaces.show', $this->workspace))->assertOk();
        $member->assertSee($this->link($memberPrivate), false)
            ->assertSee($this->link($shared), false)
            ->assertSee($this->link($public), false)
            ->assertDontSee($this->link($adminPrivate), false);

        // viewer: không thấy bảng riêng của ai
        $viewer = $this->actingAs($this->viewer)->get(route('workspaces.show', $this->workspace))->assertOk();
        $viewer->assertSee($this->link($shared), false)
            ->assertDontSee($this->link($adminPrivate), false)
            ->assertDontSee($this->link($memberPrivate), false);
    }

    public function test_khach_xem_workspace_cong_khai_chi_thay_bang_public(): void
    {
        $this->workspace->update(['visibility' => 'public']);
        $shared = $this->makeBoard('workspace', $this->admin, ['name' => 'Chung']);
        $public = $this->makeBoard('public', $this->admin, ['name' => 'Công khai']);

        $this->actingAs($this->outsider)->get(route('workspaces.show', $this->workspace))
            ->assertOk()
            ->assertSee($this->link($public), false)
            ->assertDontSee($this->link($shared), false);
    }

    public function test_chi_admin_thay_o_tao_bang_va_link_luu_tru(): void
    {
        $this->makeBoard('workspace');

        $this->actingAs($this->admin)->get(route('workspaces.show', $this->workspace))
            ->assertSee('Tạo bảng mới')
            ->assertSee(route('boards.archived', $this->workspace), false);

        foreach ([$this->member, $this->viewer] as $user) {
            $this->actingAs($user)->get(route('workspaces.show', $this->workspace))
                ->assertDontSee('Tạo bảng mới')
                ->assertDontSee(route('boards.archived', $this->workspace), false);
        }
    }

    public function test_dashboard_va_trang_bang_khong_bi_n_cong_1_khi_co_nhieu_bang(): void
    {
        // Model::preventLazyLoading đang bật ngoài production: truy cập quan hệ lười trên
        // một tập bảng sẽ ném LazyLoadingViolationException => 500.
        $boards = collect(['private', 'workspace', 'public', 'workspace', 'public'])
            ->map(fn (string $visibility, int $i) => $this->makeBoard($visibility, $this->admin, ['name' => "Bảng {$i}"]));

        $this->admin->starredBoards()->attach($boards->take(3)->pluck('id')->all());

        $this->actingAs($this->admin);
        $this->get(route('workspaces.show', $this->workspace))->assertOk();

        foreach ($boards as $board) {
            $this->get(route('boards.show', $board))->assertOk();
        }

        // Sau khi đã mở nhiều bảng: sidebar "Gần đây" và "Đã gắn sao" cùng hiển thị.
        $this->get(route('workspaces.show', $this->workspace))->assertOk()
            ->assertSee('Gần đây')
            ->assertSee('Đã gắn sao');
    }

    // ------------------------------------------------------------------
    // Vòng đời: lưu trữ / khôi phục / xóa
    // ------------------------------------------------------------------

    public function test_admin_luu_tru_va_khoi_phuc_bang(): void
    {
        $board = $this->makeBoard('workspace', null, ['name' => 'Sẽ lưu trữ']);

        $this->actingAs($this->admin)
            ->post(route('boards.archive', $board))
            ->assertRedirect(route('workspaces.show', $this->workspace));

        $this->assertNotNull($this->fresh($board)->archived_at);

        // Ẩn khỏi danh sách (Workspace::boards) nhưng hiện ở trang lưu trữ (archivedBoards).
        $this->get(route('workspaces.show', $this->workspace))->assertDontSee($this->link($board), false);
        $this->get(route('boards.archived', $this->workspace))->assertOk()->assertSee('Sẽ lưu trữ');

        $this->post(route('boards.restore', $board))
            ->assertRedirect(route('boards.archived', $this->workspace));

        $this->assertNull($this->fresh($board)->archived_at);
        $this->get(route('workspaces.show', $this->workspace))->assertSee($this->link($board), false);
    }

    public function test_khong_phai_admin_khong_luu_tru_khoi_phuc_xoa_duoc(): void
    {
        $active = $this->makeBoard('public', null, ['name' => 'Đang hoạt động']);
        $archived = $this->makeBoard('public', null, ['name' => 'Đã cất', 'archived_at' => now()]);

        foreach ($this->nonAdmins() as $user) {
            $this->actingAs($user);
            $this->post(route('boards.archive', $active))->assertForbidden();
            $this->post(route('boards.restore', $archived))->assertForbidden();
            $this->delete(route('boards.destroy', $active))->assertForbidden();
            $this->get(route('boards.archived', $this->workspace))->assertForbidden();
        }

        $this->assertNull($this->fresh($active)->archived_at);
        $this->assertNotNull($this->fresh($archived)->archived_at);
        $this->assertNull($this->fresh($active)->deleted_at);
    }

    public function test_khong_khoi_phuc_bang_dang_hoat_dong(): void
    {
        $board = $this->makeBoard('workspace');

        $this->actingAs($this->admin)->post(route('boards.restore', $board))->assertForbidden();
    }

    public function test_admin_xoa_mem_bang(): void
    {
        $active = $this->makeBoard('workspace', null, ['name' => 'Xóa từ trang workspace']);
        $archived = $this->makeBoard('workspace', null, ['name' => 'Xóa từ trang lưu trữ', 'archived_at' => now()]);

        $this->actingAs($this->admin);

        $this->delete(route('boards.destroy', $active))->assertRedirect(route('workspaces.show', $this->workspace));
        $this->delete(route('boards.destroy', $archived))->assertRedirect(route('boards.archived', $this->workspace));

        $this->assertSoftDeleted('boards', ['id' => $active->id]);
        $this->assertSoftDeleted('boards', ['id' => $archived->id]);
    }

    public function test_xoa_workspace_xoa_mem_ca_bang_dang_hoat_dong_va_da_luu_tru(): void
    {
        $active = $this->makeBoard('workspace');
        $archived = $this->makeBoard('workspace', null, ['archived_at' => now()]);

        $this->actingAs($this->admin) // chủ sở hữu
            ->delete(route('workspaces.destroy', $this->workspace))
            ->assertRedirect(route('workspaces.index'));

        $this->assertSoftDeleted('workspaces', ['id' => $this->workspace->id]);
        $this->assertSoftDeleted('boards', ['id' => $active->id]);
        $this->assertSoftDeleted('boards', ['id' => $archived->id]);
    }

    public function test_task_on_live_board_bo_qua_the_cua_bang_da_xoa(): void
    {
        $board = $this->makeBoard('workspace');
        $list = TaskList::factory()->create(['board_id' => $board->id]);
        Task::factory()->create(['task_list_id' => $list->id, 'user_id' => $this->admin->id]);

        $this->assertSame(1, Task::onLiveBoard()->count());

        $board->delete();

        // Xóa mềm bảng không lan xuống thẻ; chỉ scope onLiveBoard loại được chúng.
        $this->assertSame(1, Task::count());
        $this->assertSame(0, Task::onLiveBoard()->count());
    }

    public function test_relation_active_chi_lay_cot_va_the_chua_luu_tru(): void
    {
        $board = $this->makeBoard('workspace');
        $live = TaskList::factory()->create(['board_id' => $board->id, 'position' => 0]);
        TaskList::factory()->create(['board_id' => $board->id, 'position' => 1, 'archived_at' => now()]);

        Task::factory()->create(['task_list_id' => $live->id, 'user_id' => $this->admin->id, 'position' => 0]);
        Task::factory()->create(['task_list_id' => $live->id, 'user_id' => $this->admin->id, 'position' => 1, 'archived_at' => now()]);

        $this->assertCount(2, $board->taskLists);
        $this->assertCount(1, $board->activeTaskLists);
        $this->assertCount(1, $board->archivedTaskLists);
        $this->assertCount(2, $live->tasks);
        $this->assertCount(1, $live->activeTasks);
        $this->assertCount(1, $live->archivedTasks);
    }

    // ------------------------------------------------------------------
    // Gắn sao
    // ------------------------------------------------------------------

    public function test_gan_sao_bat_tat(): void
    {
        $board = $this->makeBoard('workspace');

        $this->actingAs($this->member)->post(route('boards.star', $board))->assertRedirect();
        $this->assertDatabaseHas('board_stars', ['user_id' => $this->member->id, 'board_id' => $board->id]);

        $this->post(route('boards.star', $board))->assertRedirect();
        $this->assertDatabaseMissing('board_stars', ['user_id' => $this->member->id, 'board_id' => $board->id]);
    }

    public function test_gan_sao_tra_json_khi_goi_bang_fetch(): void
    {
        $board = $this->makeBoard('workspace');

        $this->actingAs($this->viewer)->postJson(route('boards.star', $board))->assertOk()->assertJson(['starred' => true]);
        $this->postJson(route('boards.star', $board))->assertOk()->assertJson(['starred' => false]);
    }

    public function test_khong_gan_sao_duoc_bang_khong_xem_duoc_hoac_da_luu_tru(): void
    {
        $private = $this->makeBoard('private', $this->admin);
        $archived = $this->makeBoard('workspace', null, ['archived_at' => now()]);

        $this->actingAs($this->member2)->post(route('boards.star', $private))->assertForbidden();
        $this->actingAs($this->outsider)->post(route('boards.star', $private))->assertForbidden();
        $this->actingAs($this->admin)->post(route('boards.star', $archived))->assertForbidden();

        $this->assertDatabaseCount('board_stars', 0);
    }

    public function test_bang_gan_sao_bien_mat_khoi_sidebar_khi_mat_quyen_xem(): void
    {
        $board = $this->makeBoard('workspace', $this->admin, ['name' => 'Sẽ thành riêng tư']);
        $this->viewer->starredBoards()->attach($board->id);

        $this->actingAs($this->viewer)->get(route('workspaces.show', $this->workspace))
            ->assertOk()->assertSee('Đã gắn sao');

        $board->update(['visibility' => 'private']);

        $this->get(route('workspaces.show', $this->workspace))
            ->assertOk()->assertDontSee('Đã gắn sao');
    }
}

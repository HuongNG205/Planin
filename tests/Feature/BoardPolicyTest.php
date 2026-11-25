<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\User;
use App\Policies\BoardPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsBoardFixtures;
use Tests\TestCase;

/**
 * Ma trận BoardPolicy: (admin / member là người tạo / member khác / viewer / người ngoài)
 * x (private / workspace / public), cùng bảng đã lưu trữ và workspace đã xóa.
 */
class BoardPolicyTest extends TestCase
{
    use BuildsBoardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBoardFixtures();
    }

    private function can(User $user, string $ability, Board $board): bool
    {
        // Tải lại để mỗi lần kiểm tra dùng instance mới (không dính cache vai trò).
        return Gate::forUser($user)->allows($ability, $this->fresh($board)->load('workspace'));
    }

    /** @return array<string, array{0: string, 1: array<string, bool>}> */
    public static function viewMatrix(): array
    {
        // visibility => [người dùng => có xem được không]. 'creator' = member tạo bảng.
        return [
            'private' => ['private', ['admin' => true, 'creator' => true, 'member2' => false, 'viewer' => false, 'outsider' => false]],
            'workspace' => ['workspace', ['admin' => true, 'creator' => true, 'member2' => true, 'viewer' => true, 'outsider' => false]],
            'public' => ['public', ['admin' => true, 'creator' => true, 'member2' => true, 'viewer' => true, 'outsider' => true]],
        ];
    }

    private function actor(string $key): User
    {
        return match ($key) {
            'admin' => $this->admin,
            'creator' => $this->member,
            'member2' => $this->member2,
            'viewer' => $this->viewer,
            'outsider' => $this->outsider,
        };
    }

    /** @return array<string, array{0: string}> */
    public static function visibilities(): array
    {
        return ['private' => ['private'], 'workspace' => ['workspace'], 'public' => ['public']];
    }

    #[DataProvider('viewMatrix')]
    public function test_view_theo_visibility_va_vai_tro(string $visibility, array $expected): void
    {
        $board = $this->makeBoard($visibility, $this->member);

        foreach ($expected as $who => $allowed) {
            $this->assertSame(
                $allowed,
                $this->can($this->actor($who), 'view', $board),
                "view bảng '{$visibility}' bởi {$who} phải là ".($allowed ? 'được' : 'bị chặn')
            );
        }
    }

    #[DataProvider('visibilities')]
    public function test_chi_admin_duoc_sua_luu_tru_xoa(string $visibility): void
    {
        $board = $this->makeBoard($visibility, $this->member);

        foreach (['admin', 'creator', 'member2', 'viewer', 'outsider'] as $who) {
            $isAdmin = $who === 'admin';

            $this->assertSame($isAdmin, $this->can($this->actor($who), 'update', $board), "update bởi {$who}");
            $this->assertSame($isAdmin, $this->can($this->actor($who), 'archive', $board), "archive bởi {$who}");
            $this->assertSame($isAdmin, $this->can($this->actor($who), 'delete', $board), "delete bởi {$who}");
            // Bảng chưa lưu trữ thì không có gì để khôi phục.
            $this->assertFalse($this->can($this->actor($who), 'restore', $board), "restore bảng đang hoạt động bởi {$who}");
        }
    }

    public function test_chi_admin_duoc_tao_bang_va_vao_trang_luu_tru(): void
    {
        foreach (['admin', 'creator', 'member2', 'viewer', 'outsider'] as $who) {
            $isAdmin = $who === 'admin';
            $user = $this->actor($who);

            $this->assertSame($isAdmin, $user->can('create', [Board::class, $this->workspace->fresh()]), "create bởi {$who}");
            $this->assertSame($isAdmin, $user->can('viewArchived', [Board::class, $this->workspace->fresh()]), "viewArchived bởi {$who}");
        }
    }

    public function test_bang_da_luu_tru_chi_admin_thay_va_khoi_phuc(): void
    {
        // Dù visibility = public, bảng đã lưu trữ chỉ admin thấy.
        $board = $this->makeBoard('public', $this->member, ['archived_at' => now()]);

        foreach (['admin', 'creator', 'member2', 'viewer', 'outsider'] as $who) {
            $isAdmin = $who === 'admin';
            $user = $this->actor($who);

            $this->assertSame($isAdmin, $this->can($user, 'view', $board), "view bảng lưu trữ bởi {$who}");
            $this->assertSame($isAdmin, $this->can($user, 'restore', $board), "restore bởi {$who}");
            $this->assertSame($isAdmin, $this->can($user, 'delete', $board), "delete bảng lưu trữ bởi {$who}");
            // Bảng đã lưu trữ không sửa/lưu trữ lại/gắn sao được.
            $this->assertFalse($this->can($user, 'update', $board), "update bảng lưu trữ bởi {$who}");
            $this->assertFalse($this->can($user, 'archive', $board), "archive bảng lưu trữ bởi {$who}");
            $this->assertFalse($this->can($user, 'star', $board), "star bảng lưu trữ bởi {$who}");
        }
    }

    public function test_star_chi_khi_xem_duoc_bang(): void
    {
        $private = $this->makeBoard('private', $this->member);

        $this->assertTrue($this->can($this->member, 'star', $private));
        $this->assertTrue($this->can($this->admin, 'star', $private));
        $this->assertFalse($this->can($this->member2, 'star', $private));
        $this->assertFalse($this->can($this->outsider, 'star', $private));

        $public = $this->makeBoard('public', $this->admin);
        $this->assertTrue($this->can($this->outsider, 'star', $public));
    }

    public function test_nguoi_tao_roi_workspace_khong_con_xem_duoc_bang_private(): void
    {
        $board = $this->makeBoard('private', $this->member);
        $this->assertTrue($this->can($this->member, 'view', $board));

        $this->workspace->members()->detach($this->member->id);

        $this->assertFalse($this->can($this->member, 'view', $board));
    }

    public function test_workspace_da_xoa_mem_thi_khong_ai_xem_duoc_bang(): void
    {
        $board = $this->makeBoard('public', $this->admin);

        $this->workspace->delete();

        foreach (['admin', 'creator', 'outsider'] as $who) {
            $this->assertFalse($this->can($this->actor($who), 'view', $board), "view bởi {$who} sau khi xóa workspace");
        }
    }

    public function test_policy_duoc_tu_dong_dang_ky_cho_board(): void
    {
        $this->assertInstanceOf(BoardPolicy::class, Gate::getPolicyFor(Board::class));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bật/tắt gắn sao một bảng (board_stars). Chỉ cho phép khi người dùng có quyền XEM bảng
 * (BoardPolicy@star). Gọi từ form thường (quay lại trang trước) hoặc fetch (trả JSON).
 */
class BoardStarController extends Controller
{
    public function __construct(private readonly BoardService $boards)
    {
    }

    public function __invoke(Request $request, Board $board): RedirectResponse|JsonResponse
    {
        $this->authorize('star', $board);

        $starred = $this->boards->toggleStar($board, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['starred' => $starred]);
        }

        return back(fallback: route('boards.show', $board));
    }
}

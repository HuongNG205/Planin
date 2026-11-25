<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gom kiểm tra "phải là thành viên của {workspace}" cho cả nhóm route (xem routes/web.php).
 * Chạy sau check.login. Người ngoài workspace nhận 403, kể cả khi workspace để "công khai"
 * (công khai chỉ cho XEM, không cho thao tác thành viên).
 */
class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        // Tùy thứ tự middleware, tham số có thể chưa được binding thành model.
        if (! $workspace instanceof Workspace) {
            $workspace = Workspace::findOrFail($workspace);
        }

        $user = $request->user();

        if (! $user || ! $workspace->isMember($user)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Bạn không phải thành viên của không gian làm việc này.'], 403);
            }

            abort(403, 'Bạn không phải thành viên của không gian làm việc này.');
        }

        return $next($request);
    }
}

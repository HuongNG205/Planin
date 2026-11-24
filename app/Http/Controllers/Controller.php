<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Cho phép dùng $this->authorize('ability', $model) để gọi Policy trong controller.
    use AuthorizesRequests;
}

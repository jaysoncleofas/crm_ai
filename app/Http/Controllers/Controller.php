<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /** Clamp client-supplied page sizes so a single request can't scan the table. */
    protected function perPage(int $default = 25, int $max = 100): int
    {
        return min(max((int) request()->integer('per_page', $default), 1), $max);
    }
}

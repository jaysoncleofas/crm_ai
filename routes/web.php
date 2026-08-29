<?php

use Illuminate\Support\Facades\Route;

/*
| The Vue SPA owns client-side routing; every non-API path returns the same
| shell and the router takes over. `{any}` allows slashes so deep links like
| /contacts/42 resolve, while the lookahead keeps API and asset paths out.
*/
Route::view('/', 'app')->name('spa');

Route::view('/{any}', 'app')
    ->where('any', '^(?!api|sanctum|storage|build|up).*$')
    ->name('spa.catchall');

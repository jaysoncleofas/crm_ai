<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM API
|--------------------------------------------------------------------------
| Every route sits behind the `api` throttle (see bootstrap/app.php).
| Auth endpoints get a much stricter limiter; write endpoints get their own.
*/

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
});

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Reads
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::get('deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('deals/board', [DealController::class, 'board'])->name('deals.board');
    Route::get('deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::get('pipelines/{pipeline}', [PipelineController::class, 'show'])->name('pipelines.show');
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    // Writes — tighter limiter than reads.
    Route::middleware('throttle:mutations')->group(function (): void {
        Route::apiResource('contacts', ContactController::class)->except(['index', 'show']);
        Route::post('contacts/{contact}/restore', [ContactController::class, 'restore'])
            ->whereNumber('contact')->name('contacts.restore');

        Route::apiResource('companies', CompanyController::class)->except(['index', 'show']);
        Route::post('companies/{company}/restore', [CompanyController::class, 'restore'])
            ->whereNumber('company')->name('companies.restore');

        Route::apiResource('deals', DealController::class)->except(['index', 'show']);
        Route::post('deals/{deal}/restore', [DealController::class, 'restore'])
            ->whereNumber('deal')->name('deals.restore');
        Route::patch('deals/{deal}/stage', [DealController::class, 'moveStage'])->name('deals.stage');

        Route::apiResource('activities', ActivityController::class)->except(['index', 'show']);
        Route::post('activities/{activity}/restore', [ActivityController::class, 'restore'])
            ->whereNumber('activity')->name('activities.restore');

        Route::apiResource('pipelines', PipelineController::class)->except(['index', 'show']);
        Route::apiResource('tags', TagController::class)->except(['index', 'show']);

        Route::apiResource('users', UserController::class)->except(['index', 'show']);
        Route::post('users/{user}/restore', [UserController::class, 'restore'])
            ->whereNumber('user')->name('users.restore');
    });
});

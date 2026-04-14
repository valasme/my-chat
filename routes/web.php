<?php

use App\Http\Controllers\BlockController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IgnoreController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserTimezoneController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::post('user/timezone', [UserTimezoneController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user.timezone');

    Route::get('dashboard', DashboardController::class)
        ->middleware('throttle:chat-read')
        ->name('dashboard');

    Route::middleware('throttle:chat-read')->group(function () {
        Route::resource('contacts', ContactController::class)->only(['index', 'show', 'create']);
        Route::resource('conversations', ConversationController::class)->only(['index', 'show']);
        Route::resource('blocks', BlockController::class)->only(['index']);
        Route::resource('ignores', IgnoreController::class)->only(['index']);
        Route::resource('trashes', TrashController::class)->only(['index']);
    });

    Route::middleware('throttle:chat-write')->group(function () {
        Route::resource('contacts', ContactController::class)->only(['store', 'update', 'destroy']);
        Route::resource('blocks', BlockController::class)->only(['store', 'destroy']);
        Route::resource('ignores', IgnoreController::class)->only(['store', 'destroy']);
        Route::resource('trashes', TrashController::class)->only(['store', 'destroy']);
        Route::delete('trashes/{trash}/force', [TrashController::class, 'forceDelete'])->name('trashes.force-delete');
    });

});

require __DIR__.'/settings.php';

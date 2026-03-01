<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::resource('contacts', ContactController::class)->only([
        'index', 'create', 'store', 'show', 'destroy',
    ]);
});

require __DIR__.'/settings.php';

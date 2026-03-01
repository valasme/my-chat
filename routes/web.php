<?php

/**
 * Web Routes
 *
 * All routes served by the application's web middleware group.
 *
 * Public routes:
 *   GET /               → welcome page
 *
 * Authenticated & verified routes:
 *   GET /dashboard      → user dashboard
 *   GET    /contacts           → contacts.index   (list with search/sort/pagination)
 *   GET    /contacts/create    → contacts.create   (add contact form)
 *   POST   /contacts           → contacts.store    (search by email & save)
 *   GET    /contacts/{contact} → contacts.show     (contact profile detail)
 *   DELETE /contacts/{contact} → contacts.destroy  (remove contact)
 *
 * Settings routes loaded from routes/settings.php.
 *
 * @see \App\Http\Controllers\ContactController
 */

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

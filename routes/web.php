<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// The app has no marketing page: the front door is the login screen.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/crm.php';
require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;

// Route::get('/dashboard', [DashboardController::class, 'view']);
// Route::post('/dashboard', [DashboardController::class, 'view']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $title = "Dashboard";
    return view('dashboard', compact("title"));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'view'])->name('dashboard');
    Route::post('/dashboard', [DashboardController::class, 'view'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource("notes", NoteController::class)->middleware(['auth', 'verified']);

require __DIR__ . '/auth.php';

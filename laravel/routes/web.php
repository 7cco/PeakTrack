<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\GitHubAuthController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/auth/github', [GitHubAuthController::class, 'redirectToGithub'])->name('auth.github');
Route::get('/auth/github/callback', [GitHubAuthController::class, 'handleGithubCallback']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Главная страница со списком привычек
    Route::get('/habits', [HabitController::class, 'index'])->name('habits.index');
    // Создание новой привычки
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    
    Route::put('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    // Логирование выполнения
    Route::post('/habits/{habit}/log', [HabitController::class, 'log'])->name('habits.log');
    Route::get('/habits/{habit}', [HabitController::class, 'show'])->name('habits.show');
});

require __DIR__.'/auth.php';

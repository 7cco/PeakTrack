<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HabitController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Главная страница со списком привычек
    Route::get('/habits', [HabitController::class, 'index'])->name('habits.index');
    // Создание новой привычки
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    // Логирование выполнения
    Route::post('/habits/{habit}/log', [HabitController::class, 'log'])->name('habits.log');
});

require __DIR__.'/auth.php';

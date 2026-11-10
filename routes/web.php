<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookController::class, 'index'])->name('home');
Route::patch('/books/{book}', [BookController::class, 'update'])->name('books.update');
Route::patch('/books/{book}/toggle', [BookController::class, 'toggle'])->name('books.toggle');
Route::post('/books', [BookController::class, 'store'])->name('books.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

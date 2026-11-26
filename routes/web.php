<?php

// A02 · Карта маршрутов. Полный список — docs/REGISTRY.md §Маршруты.

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReviewController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Мастер установки (доступен только до установки — см. EnsureInstalled)
Route::prefix('install')->name('install.')->controller(InstallController::class)->group(function () {
    Route::get('/', 'welcome')->name('welcome');
    Route::get('/database', 'database')->name('database');
    Route::post('/database/test', 'testDatabase')->name('database.test');
    Route::post('/database', 'saveDatabase')->name('database.save');
    Route::get('/site', 'site')->name('site');
    Route::post('/site', 'finish')->name('finish');
});

// Витрина
Route::get('/', HomeController::class)->name('home');

// A14 · Вклады: каталог, сравнение, карточка (compare — до {product}, иначе слово съест маршрут карточки)
Route::prefix('deposits')->name('deposits.')->controller(DepositController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/compare', 'compare')->name('compare');
    Route::get('/{product:slug}', 'show')->name('show');
});

// A18 · Банки, рейтинги, отзывы
Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
Route::get('/banks/{bank:slug}', [BankController::class, 'show'])->name('banks.show');
Route::get('/ratings', RatingController::class)->name('ratings');
Route::middleware('auth')->group(function () {
    Route::post('/banks/{bank:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:6,1')->name('reviews.store');
    Route::post('/reviews/{review}/vote', [ReviewController::class, 'vote'])->middleware('throttle:30,1')->name('reviews.vote');
});

// Вход и регистрация (A18)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Админка
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::get('/banks', [Admin\BankController::class, 'index'])->name('banks.index');
    Route::get('/banks/{bank}/edit', [Admin\BankController::class, 'edit'])->name('banks.edit');
    Route::put('/banks/{bank}', [Admin\BankController::class, 'update'])->name('banks.update');
    Route::get('/import', [Admin\ImportController::class, 'index'])->name('import.index');
    Route::post('/import/preview', [Admin\ImportController::class, 'preview'])->name('import.preview');
    Route::post('/import', [Admin\ImportController::class, 'store'])->name('import.store');
    // A18 · Рейтинги и модерация отзывов
    Route::get('/ratings', [Admin\RatingController::class, 'index'])->name('ratings.index');
    Route::post('/ratings/snapshot', [Admin\RatingController::class, 'snapshot'])->name('ratings.snapshot');
    Route::get('/ratings/{bank}/edit', [Admin\RatingController::class, 'edit'])->name('ratings.edit');
    Route::put('/ratings/{bank}', [Admin\RatingController::class, 'update'])->name('ratings.update');
    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::put('/reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

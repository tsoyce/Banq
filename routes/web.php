<?php

// A02 · Карта маршрутов. Полный список — docs/REGISTRY.md §Маршруты.

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Install\InstallController;
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

// Вход
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
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
});

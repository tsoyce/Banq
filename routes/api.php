<?php

// A23 · API обмена данными. Токен — /admin/exchange/tokens; описание — docs/API.md.

use App\Http\Controllers\Api\ExchangeController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware('throttle:120,1')->group(function () {
    Route::middleware(AuthenticateApiToken::class.':read')->group(function () {
        Route::get('/schema', [ExchangeController::class, 'schema'])->name('schema');
        Route::get('/export', [ExchangeController::class, 'export'])->name('export');
        Route::get('/imports', [ExchangeController::class, 'imports'])->name('imports');
        Route::get('/imports/{import}', [ExchangeController::class, 'showImport'])->name('imports.show');
    });
    Route::post('/import', [ExchangeController::class, 'import'])->middleware(AuthenticateApiToken::class.':write')->name('import');
    Route::get('/{entity}', [ExchangeController::class, 'index'])->middleware(AuthenticateApiToken::class.':read')->name('entity');
});

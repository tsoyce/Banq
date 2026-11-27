<?php

// A23 · API обмена данными. A39 — руководство для агентов, OpenAPI, POST/DELETE по сущности. Токен — /admin/exchange/tokens; описание — docs/API.md.

use App\Http\Controllers\Api\ExchangeController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware('throttle:120,1')->group(function () {
    // A39 · без токена: руководство для агентов и OpenAPI
    Route::get('/', [ExchangeController::class, 'about'])->name('about');
    Route::get('/openapi.json', [ExchangeController::class, 'openapi'])->name('openapi');

    Route::middleware(AuthenticateApiToken::class.':read')->group(function () {
        Route::get('/schema', [ExchangeController::class, 'schema'])->name('schema');
        Route::get('/export', [ExchangeController::class, 'export'])->name('export');
        Route::get('/imports', [ExchangeController::class, 'imports'])->name('imports');
        Route::get('/imports/{import}', [ExchangeController::class, 'showImport'])->name('imports.show');
    });
    Route::post('/import', [ExchangeController::class, 'import'])->middleware(AuthenticateApiToken::class.':write')->name('import');
    Route::get('/{entity}', [ExchangeController::class, 'index'])->middleware(AuthenticateApiToken::class.':read')->name('entity');
    // A39 · строки одной сущности: upsert и удаление (право delete проверяется в контроллере)
    Route::post('/{entity}', [ExchangeController::class, 'upsert'])->middleware(AuthenticateApiToken::class.':write')->name('entity.upsert');
    Route::delete('/{entity}', [ExchangeController::class, 'destroy'])->middleware(AuthenticateApiToken::class.':write')->name('entity.destroy');
});

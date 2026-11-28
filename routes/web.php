<?php

// A02 · Карта маршрутов. Полный список — docs/REGISTRY.md §Маршруты.

use App\Admin\ResourceRegistry;
use App\Http\Controllers\Account;
use App\Http\Controllers\AdController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentSystemController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServicePackageController;
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

// A33 · Карты, платёжные системы, пакеты услуг (compare — до {card})
Route::prefix('cards')->name('cards.')->controller(CardController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/compare', 'compare')->name('compare');
    Route::get('/{card:slug}', 'show')->name('show');
});
Route::get('/payment-systems', [PaymentSystemController::class, 'index'])->name('payment-systems.index');
Route::get('/payment-systems/{system:slug}', [PaymentSystemController::class, 'show'])->name('payment-systems.show');
Route::get('/packages', [ServicePackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [ServicePackageController::class, 'show'])->name('packages.show');

// A18 · Банки, рейтинги, отзывы
Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
Route::get('/banks/{bank:slug}', [BankController::class, 'show'])->name('banks.show');
Route::get('/ratings', RatingController::class)->name('ratings');
Route::middleware('auth')->group(function () {
    Route::post('/banks/{bank:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:6,1')->name('reviews.store');
    Route::post('/reviews/{review}/vote', [ReviewController::class, 'vote'])->middleware('throttle:30,1')->name('reviews.vote');
});

// A37 · Блог: лента, рубрики, теги, RSS, публикация; реакции и комментарии — для вошедших
Route::prefix('blog')->name('blog.')->controller(BlogController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/rss.xml', 'rss')->name('rss');
    Route::get('/c/{category:slug}', 'category')->name('category');
    Route::get('/tag/{tag:slug}', 'tag')->name('tag');
    Route::get('/{post:slug}', 'show')->name('show');
    Route::middleware('auth')->group(function () {
        Route::post('/{post:slug}/react', 'react')->middleware('throttle:blog-reactions')->name('react');
        Route::post('/{post:slug}/comments', 'comment')->middleware('throttle:blog-comments')->name('comments.store');
        Route::delete('/comments/{comment}', 'destroyComment')->name('comments.destroy');
    });
});

// A28 · Личный кабинет, избранное, сохранённые сравнения, публичный профиль автора
Route::get('/u/{username}', [ProfileController::class, 'show'])->where('username', '[A-Za-z0-9-]{3,40}')->name('profile.show');
Route::middleware('auth')->group(function () {
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/', [Account\AccountController::class, 'index'])->name('index');
        Route::get('/reviews', [Account\AccountController::class, 'reviews'])->name('reviews');
        Route::get('/favorites', [Account\AccountController::class, 'favorites'])->name('favorites');
        Route::get('/comparisons', [Account\AccountController::class, 'comparisons'])->name('comparisons');
        Route::get('/settings', [Account\AccountController::class, 'settings'])->name('settings');
        Route::put('/settings/profile', [Account\SettingsController::class, 'profile'])->middleware('throttle:20,1')->name('settings.profile');
        Route::delete('/settings/avatar', [Account\SettingsController::class, 'destroyAvatar'])->name('settings.avatar.destroy');
        Route::put('/settings/email', [Account\SettingsController::class, 'email'])->middleware('throttle:6,1')->name('settings.email');
        Route::put('/settings/password', [Account\SettingsController::class, 'password'])->middleware('throttle:6,1')->name('settings.password');
        Route::put('/settings/notifications', [Account\SettingsController::class, 'notifications'])->name('settings.notifications');
        Route::delete('/', [Account\SettingsController::class, 'destroy'])->middleware('throttle:3,1')->name('destroy');
    });
    Route::post('/favorites', [FavoriteController::class, 'toggle'])->middleware('throttle:60,1')->name('favorites.toggle');
    Route::post('/comparisons', [ComparisonController::class, 'store'])->middleware('throttle:20,1')->name('comparisons.store');
    Route::put('/comparisons/{comparison}', [ComparisonController::class, 'update'])->name('comparisons.update');
    Route::delete('/comparisons/{comparison}', [ComparisonController::class, 'destroy'])->name('comparisons.destroy');
});

// A55 · Страница автора (основатель TSOY.IN): профиль, соцсети, публикации, поддержка; /donate — короткая ссылка на блок поддержки
Route::get('/author', [AuthorController::class, 'show'])->name('author');
Route::redirect('/donate', '/author#donate', 302)->name('donate');

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

    // A22/A23 · Обмен данными: импорт (dry-run → применить), экспорт, схема, токены API, история
    Route::prefix('exchange')->name('exchange.')->controller(Admin\ExchangeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/check', 'check')->name('check');
        Route::post('/apply', 'apply')->name('apply');
        Route::get('/export', 'export')->name('export');
        Route::get('/schema', 'schema')->name('schema');
        Route::get('/template/{entity}/{format}', 'template')->name('template');
        Route::get('/history/{import}', 'show')->name('show');
        Route::get('/tokens', 'tokens')->name('tokens');
        Route::post('/tokens', 'issueToken')->name('tokens.store');
        Route::delete('/tokens/{token}', 'revokeToken')->name('tokens.destroy');
    });
    // A39 · Табличный редактор («как Excel»): строки любой сущности, вставка из Excel, сохранение через движок обмена
    Route::prefix('exchange/grid')->name('exchange.grid')->controller(Admin\GridController::class)->group(function () {
        Route::get('/', 'index')->name('.index');
        Route::get('/{entity}', 'show')->name('');
        Route::get('/{entity}/rows', 'rows')->name('.rows');
        Route::post('/{entity}', 'save')->middleware('throttle:60,1')->name('.save');
    });

    // A40 · Бегущая строка и официальные курсы валют
    Route::get('/ticker', [Admin\TickerController::class, 'edit'])->name('ticker.edit');
    Route::put('/ticker', [Admin\TickerController::class, 'update'])->name('ticker.update');
    Route::get('/currency', [Admin\CurrencyController::class, 'index'])->name('currency.index');
    Route::put('/currency', [Admin\CurrencyController::class, 'update'])->name('currency.update');
    Route::post('/currency/{country}/sync', [Admin\CurrencyController::class, 'sync'])->middleware('throttle:10,1')->name('currency.sync');

    // A55 · Автор, соцсети (счётчики подписчиков), поддержка проекта
    Route::get('/author', [Admin\AuthorController::class, 'edit'])->name('author.edit');
    Route::put('/author', [Admin\AuthorController::class, 'update'])->name('author.update');
    Route::post('/author/refresh', [Admin\AuthorController::class, 'refresh'])->middleware('throttle:10,1')->name('author.refresh');

    // A34 · База данных: переключение SQLite ⇄ MySQL с переносом данных (только admin)
    Route::get('/database', [Admin\DatabaseController::class, 'index'])->name('database.index');
    Route::post('/database/test', [Admin\DatabaseController::class, 'test'])->middleware('throttle:20,1')->name('database.test');
    Route::post('/database/switch', [Admin\DatabaseController::class, 'switch'])->middleware('throttle:5,1')->name('database.switch');
    Route::post('/database/restore', [Admin\DatabaseController::class, 'restore'])->middleware('throttle:5,1')->name('database.restore');

    // A37 · Редактор публикаций: загрузка картинок, поиск связей и вставок, превью вставки
    Route::post('/blog/upload', [Admin\BlogController::class, 'upload'])->middleware('throttle:60,1')->name('blog.upload');
    Route::get('/blog/lookup', [Admin\BlogController::class, 'lookup'])->name('blog.lookup');
    Route::get('/blog/embed', [Admin\BlogController::class, 'embed'])->name('blog.embed');

    // A43 · Перенос с WordPress: пробный прогон, шаги переноса, отчёт (только admin — проверка в контроллере)
    Route::prefix('wordpress')->name('wordpress.')->controller(Admin\WordPressController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/scan', 'scan')->middleware('throttle:10,1')->name('scan');
        Route::post('/step', 'step')->middleware('throttle:120,1')->name('step');
        Route::get('/report', 'report')->name('report');
        Route::post('/reset', 'reset')->name('reset');
    });

    // A51 · Реклама: обзор, статистика, настройки показа и ads.txt
    Route::get('/ads', [Admin\AdsController::class, 'index'])->name('ads.index');
    Route::put('/ads', [Admin\AdsController::class, 'update'])->name('ads.update');
    // A46 · Страна в плашке админки и библиотека флагов (свои флаги — загрузка/замена)
    Route::post('/country', Admin\CountryScopeController::class)->name('country');
    Route::get('/flags', [Admin\FlagController::class, 'index'])->name('flags.index');
    Route::post('/flags', [Admin\FlagController::class, 'store'])->middleware('throttle:30,1')->name('flags.store');
    Route::delete('/flags/{code}', [Admin\FlagController::class, 'destroy'])->where('code', '[a-z0-9-]+')->name('flags.destroy');

    // A24 · Глобальный поиск по админке
    Route::get('/search', Admin\SearchController::class)->name('search');

    // A24 · Универсальный CRUD: страны, банки, вклады, ставки, пользователи; A32 — карты, пакеты, платёжные системы, уровни (App\Admin\ResourceRegistry)
    Route::prefix('{key}')->name('resource.')->whereIn('key', app(ResourceRegistry::class)->keys())->controller(Admin\ResourceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });
});

// A51 · Своя реклама: учёт показов и «Скрыть», переход по объявлению. A50 · ads.txt
Route::post('/ads/e', [AdController::class, 'event'])->middleware('throttle:120,1')->name('ads.event');
Route::get('/ads/c/{token}', [AdController::class, 'click'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9.\-]+')->name('ads.click');
Route::get('/ads.txt', [AdController::class, 'adsTxt'])->name('ads.txt');

// A42 · Страницы по адресу /{slug} и редиректы со старых адресов (WordPress tsoy.in, A43) — после всех маршрутов
Route::fallback(PageController::class)->name('page.fallback');

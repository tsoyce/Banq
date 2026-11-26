<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** A09 · Вход/выход. Регистрация — RegisterController (A18). */
class LoginController extends Controller
{
    public function show(Request $request): View
    {
        // A18 · ?to=/banks/kaspi#review-form — вернуть туда после входа (только относительные пути)
        $to = (string) $request->query('to');
        if (str_starts_with($to, '/') && ! str_starts_with($to, '//')) {
            $request->session()->put('url.intended', url($to));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Слишком много попыток. Подожди '.RateLimiter::availableIn($key).' сек.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // A18 · сотрудники — в админку, посетители — обратно на сайт
        return redirect()->intended($request->user()->isStaff() ? route('admin.dashboard') : route('account.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** A18 · Регистрация посетителей (роль user) — чтобы оставлять отзывы и голосовать «полезно». */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'agree' => ['accepted'],
        ], [
            'email.unique' => 'Этот email уже зарегистрирован — войдите.',
            'agree.accepted' => 'Нужно согласиться с правилами отзывов.',
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'user']);
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('ratings'));
    }
}

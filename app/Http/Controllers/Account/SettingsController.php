<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Countries;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** A28 · Настройки аккаунта: профиль и аватар, почта, пароль, уведомления, удаление аккаунта. */
class SettingsController extends Controller
{
    /** Аватары — рядом с брендингом, в public (на сервере нет storage:link). */
    public const AVATAR_DIR = 'uploads/avatars';

    public function profile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:'.Username::PATTERN, Rule::notIn(Username::RESERVED), Rule::unique('users', 'username')->ignore($user->id)],
            'city' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', Rule::in(array_keys(Countries::LIST))],
            'bio' => ['nullable', 'string', 'max:280'],
            'is_public' => ['boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ], [
            'username.regex' => 'Адрес — латиница, цифры и дефис, от 3 символов.',
            'username.unique' => 'Этот адрес уже занят.',
            'username.not_in' => 'Это служебное слово — выберите другой адрес.',
            'avatar.max' => 'Фото до 2 МБ.',
        ]);

        $data['is_public'] = $request->boolean('is_public');

        if ($request->hasFile('avatar')) {
            $this->removeAvatar($user);
            $file = $request->file('avatar');
            $name = $user->id.'-'.Str::random(8).'.'.$file->extension();
            File::ensureDirectoryExists(public_path(self::AVATAR_DIR));
            $file->move(public_path(self::AVATAR_DIR), $name);
            $data['avatar'] = self::AVATAR_DIR.'/'.$name;
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return back()->with('status', 'Профиль сохранён');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $this->removeAvatar($request->user());
        $request->user()->update(['avatar' => null]);

        return back()->with('status', 'Фото удалено');
    }

    public function email(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('email', [
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ], ['email.unique' => 'Этот email уже зарегистрирован.', 'current_password.current_password' => 'Неверный пароль.']);

        if (Str::lower($data['email']) !== Str::lower($user->email)) {
            $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
        }

        return back()->with('status', 'Email обновлён');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], ['current_password.current_password' => 'Неверный текущий пароль.', 'password.different' => 'Новый пароль совпадает с текущим.']);

        $request->user()->update(['password' => $data['password']]);
        // Остальные устройства выходят, текущая сессия остаётся
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return back()->with('status', 'Пароль изменён, другие устройства вышли из аккаунта');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify' => collect(User::NOTIFY)->map(fn ($_, $key) => $request->boolean('notify.'.$key))->all(),
        ]);

        return back()->with('status', 'Уведомления сохранены');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === 'admin', 403, 'Администратора удаляют из базы вручную.');
        $request->validateWithBag('delete', [
            'current_password' => ['required', 'current_password'],
            'confirm' => ['accepted'],
        ], ['current_password.current_password' => 'Неверный пароль.', 'confirm.accepted' => 'Подтвердите удаление.']);

        $this->removeAvatar($user);
        Auth::logout();
        $user->delete();   // отзывы, голоса, избранное и сравнения удаляются каскадом
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Аккаунт удалён');
    }

    private function removeAvatar(User $user): void
    {
        if ($user->avatar && str_starts_with($user->avatar, self::AVATAR_DIR.'/')) {
            File::delete(public_path($user->avatar));
        }
    }
}

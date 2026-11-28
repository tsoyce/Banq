<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Support\Flags;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A46 · Библиотека флагов: все SVG flag-icons с поиском, свои флаги (замена библиотечного или новый код —
 * регион, организация, старый вариант) и ручной ввод названия. Хранение — настройка flags.custom, файлы — public/uploads/flags.
 */
class FlagController extends Controller
{
    public function index(): View
    {
        return view('admin.flags.index', [
            'flags' => Flags::all(),
            'used' => Country::query()->ordered()->get()->mapWithKeys(fn (Country $c) => [$c->flagCode() => $c->name])->all(),
        ]);
    }

    public function store(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'regex:/^[a-zA-Z]{2,8}(-[a-zA-Z0-9]{1,8})?$/'],
            'name' => ['nullable', 'string', 'max:80'],
            'file' => ['nullable', 'file', 'max:512', 'extensions:svg,png,jpg,jpeg,webp'],
        ], ['code.regex' => 'Код — латиница: kz, eu, gb-sct.']);
        $code = Flags::normalize($data['code']);
        $custom = Flags::custom();
        $entry = $custom[$code] ?? [];

        if ($request->file('file')) {
            try {
                $entry['url'] = Flags::store($request->file('file'), $code);
            } catch (\InvalidArgumentException $e) {
                return back()->withInput()->withErrors(['file' => $e->getMessage()]);
            }
        }
        if (filled($data['name'] ?? null)) {
            $entry['name'] = trim($data['name']);
        }
        if (! isset($entry['url']) && ! isset(Flags::NAMES[$code])) {
            return back()->withInput()->withErrors(['file' => 'Такого кода нет в библиотеке — загрузите картинку флага.']);
        }
        if (! $entry) {
            return back()->withInput()->withErrors(['name' => 'Загрузите картинку или впишите название.']);
        }
        $custom[$code] = $entry;
        ksort($custom);
        $settings->set([Flags::SETTING => $custom], 'flags');

        return redirect()->route('admin.flags.index', ['q' => $code])->with('status', 'Флаг «'.($entry['name'] ?? Flags::name($code)).'» сохранён');
    }

    /** Убрать свой флаг: библиотечный код вернётся к SVG из библиотеки, свой код исчезнет. */
    public function destroy(string $code, Settings $settings): RedirectResponse
    {
        $custom = Flags::custom();
        $entry = $custom[$code] ?? null;
        abort_if($entry === null, 404);
        unset($custom[$code]);
        $settings->set([Flags::SETTING => $custom], 'flags');
        if (isset($entry['url']) && str_starts_with($entry['url'], '/'.Flags::DIR.'/')) {
            @unlink(public_path(ltrim($entry['url'], '/')));
        }

        return back()->with('status', isset(Flags::NAMES[$code]) ? 'Вернули флаг из библиотеки' : 'Свой флаг удалён');
    }
}

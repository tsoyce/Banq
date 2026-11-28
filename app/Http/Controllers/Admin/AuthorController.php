<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Social\SocialStats;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A46 · Админка → Сайт → «Автор и соцсети»: кто автор страницы /author и что о нём написано,
 * список соцсетей (порядок, ручные числа, видимость), ключи API счётчиков, способы поддержать проект.
 */
class AuthorController extends Controller
{
    public function edit(SocialStats $social): View
    {
        $keys = (array) settings('social.keys', []);

        return view('admin.author.edit', [
            'staff' => User::query()->whereIn('role', ['admin', 'editor'])->orderBy('name')->get(['id', 'name', 'role']),
            'founder' => app(\App\Support\Founder::class)->user(),
            'networks' => collect(SocialStats::NETWORKS)->map(fn ($n) => ['label' => $n['label'], 'auto' => $n['auto']])->all(),
            'rows' => array_values((array) settings('social.networks', [])),
            'status' => collect($social->profiles(all: true))->keyBy('network')->all(),
            'refreshedAt' => settings('social.refreshed_at'),
            'donations' => array_values((array) settings('donate.items', [])),
            // Ключи наружу не отдаём: только признак «задан» и откуда
            'keys' => collect(['youtube', 'telegram', 'vk'])->mapWithKeys(fn ($k) => [$k => ! empty($keys[$k]) ? 'admin' : (config("banq.social.keys.{$k}") ? 'env' : null)])->all(),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['admin', 'editor'])],
            'headline' => ['nullable', 'string', 'max:120'],
            'slogan' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:5000'],
            'social_enabled' => ['nullable', 'boolean'],
            'networks' => ['nullable', 'array', 'max:20'],
            'networks.*.network' => ['required', Rule::in(array_keys(SocialStats::NETWORKS))],
            'networks.*.url' => ['required', 'url:https,http', 'max:300'],
            'networks.*.label' => ['nullable', 'string', 'max:40'],
            'networks.*.followers' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'networks.*.visible' => ['nullable', 'boolean'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['nullable', 'string', 'max:300'],
            'clear_keys' => ['nullable', 'array'],
            'donate_enabled' => ['nullable', 'boolean'],
            'donate_title' => ['nullable', 'string', 'max:80'],
            'donate_text' => ['nullable', 'string', 'max:600'],
            'donations' => ['nullable', 'array', 'max:12'],
            'donations.*.label' => ['required', 'string', 'max:60'],
            'donations.*.currency' => ['nullable', 'string', 'max:8'],
            'donations.*.url' => ['nullable', 'url:https,http', 'max:300', 'required_without:donations.*.details'],
            'donations.*.details' => ['nullable', 'string', 'max:200'],
            'donations.*.note' => ['nullable', 'string', 'max:120'],
        ], [
            'donations.*.url.required_without' => 'У способа поддержки нужна ссылка или реквизиты.',
            'networks.*.url.url' => 'Адрес соцсети должен начинаться с https://',
        ]);

        // Ключи API: пустое поле — оставить прежний, галочка «удалить» — стереть
        $keys = (array) settings('social.keys', []);
        foreach (['youtube', 'telegram', 'vk'] as $k) {
            if (filled($data['keys'][$k] ?? null)) {
                $keys[$k] = trim($data['keys'][$k]);
            }
            if (in_array($k, (array) ($data['clear_keys'] ?? []), true)) {
                unset($keys[$k]);
            }
        }

        $settings->set([
            'author.user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'author.headline' => $data['headline'] ?? null,
            'author.slogan' => $data['slogan'] ?? null,
            'author.about' => $data['about'] ?? null,
            'social.enabled' => $request->boolean('social_enabled'),
            'social.networks' => collect($data['networks'] ?? [])->map(fn ($n) => [
                'network' => $n['network'],
                'url' => trim($n['url']),
                'label' => filled($n['label'] ?? null) ? trim($n['label']) : null,
                'followers' => isset($n['followers']) && $n['followers'] !== '' ? (int) $n['followers'] : null,
                'visible' => (bool) ($n['visible'] ?? false),
            ])->values()->all(),
            'social.keys' => $keys,
            'social.refreshed_at' => null, // новые адреса/ключи — пересчитать при следующем заходе
            'donate.enabled' => $request->boolean('donate_enabled'),
            'donate.title' => $data['donate_title'] ?? null,
            'donate.text' => $data['donate_text'] ?? null,
            'donate.items' => collect($data['donations'] ?? [])->map(fn ($d) => [
                'label' => trim($d['label']),
                'currency' => filled($d['currency'] ?? null) ? mb_strtoupper(trim($d['currency'])) : null,
                'url' => filled($d['url'] ?? null) ? trim($d['url']) : null,
                'details' => filled($d['details'] ?? null) ? trim($d['details']) : null,
                'note' => filled($d['note'] ?? null) ? trim($d['note']) : null,
            ])->values()->all(),
        ], 'author');

        return back()->with('status', 'Автор, соцсети и поддержка сохранены');
    }

    public function refresh(SocialStats $social): RedirectResponse
    {
        $result = $social->refresh();
        $errors = collect($result)->filter(fn ($r) => $r['error'])->map(fn ($r, $k) => SocialStats::NETWORKS[$k]['label'].': '.$r['error']);
        $ok = collect($result)->filter(fn ($r) => $r['count'] !== null && ! $r['error'])->count();

        return back()->with('status', $errors->isEmpty() ? 'Счётчики обновлены: '.$ok : 'Обновлено: '.$ok.'. Есть ошибки — см. таблицу')
            ->with('social_errors', $errors->values()->all());
    }
}

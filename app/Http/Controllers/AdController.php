<?php

namespace App\Http\Controllers;

use App\Models\AdCreative;
use App\Services\Ads\AdServer;
use App\Services\Ads\Snippets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A51 · Публичные адреса рекламы: учёт показов и «Скрыть» (POST /ads/e), переход по объявлению (GET /ads/c/{метка}).
 * A50 · /ads.txt — текст из админки + строки AdSense из «Кодов и счётчиков».
 */
class AdController extends Controller
{
    public function __construct(private AdServer $ads) {}

    /** {v: [метки, которые стали видны], h: метка «скрыть»} → 204 + обновлённые cookie частоты и скрытых. */
    public function event(Request $request): Response
    {
        $response = response()->noContent();
        $views = array_slice(array_filter((array) $request->input('v', []), 'is_string'), 0, 10);
        $freq = $this->ads->frequency($request);
        $changed = false;

        foreach ($views as $token) {
            $t = $this->ads->verify($token, 2 * 3600);
            if (! $t || ! $this->ads->countable($request) || ! $this->ads->record($t, 'impression')) {
                continue;
            }
            $freq[$t['campaign']] = ($freq[$t['campaign']] ?? 0) + 1;
            $changed = true;
        }
        if ($changed) {
            $response->headers->setCookie(cookie(AdServer::FREQ_COOKIE, $this->ads->frequencyCookie($freq), 60 * 24, sameSite: 'lax'));
        }

        if (is_string($hide = $request->input('h')) && ($t = $this->ads->verify($hide, 7 * 86400))) {
            $hidden = array_slice(array_unique([...$this->ads->hidden($request), $t['campaign']]), -30);
            $response->headers->setCookie(cookie(AdServer::HIDE_COOKIE, implode(',', $hidden), 60 * 24 * 30, sameSite: 'lax'));
        }

        return $response;
    }

    /** Клик: метка проверяется, засчитывается один раз, затем 302 на адрес рекламодателя. */
    public function click(Request $request, string $token): RedirectResponse
    {
        $t = $this->ads->verify($token, 7 * 86400);
        $creative = $t ? AdCreative::query()->whereKey($t['creative'])->where('campaign_id', $t['campaign'])->first() : null;
        abort_unless($creative && preg_match('#^https?://#i', (string) $creative->url), 404);

        if ($this->ads->countable($request)) {
            $this->ads->record($t, 'click');
        }

        return redirect()->away($creative->url, 302, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function adsTxt(Snippets $snippets): Response
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/u', (string) settings('ads.txt', ''))));
        foreach ($snippets->adsTxtLines() as $line) {
            if (! collect($lines)->contains(fn ($l) => strcasecmp(preg_replace('/\s+/', '', $l), preg_replace('/\s+/', '', $line)) === 0)) {
                $lines[] = $line;
            }
        }
        abort_if(! $lines, 404);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

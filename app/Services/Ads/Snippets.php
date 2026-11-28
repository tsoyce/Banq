<?php

namespace App\Services\Ads;

use App\Models\CodeSnippet;
use App\Support\Installation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * A50 · Вставки сторонних кодов на витрину (layouts/app): <head>, сразу после <body>, перед </body>.
 * Готовые сервисы собираются из шаблонов по идентификатору (ничего, кроме проверенного ID, в код не попадает);
 * «Свой код» — как есть. Учитываются страна витрины и «не грузить для сотрудников». В админке кодов нет.
 */
class Snippets
{
    public const CACHE_KEY = 'banq.snippets';

    private ?array $list = null;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        if (app()->resolved(self::class)) {
            app(self::class)->list = null;
        }
    }

    /** Активные вставки (массивы — кешируются навсегда, сбрасываются при сохранении). */
    public function all(): array
    {
        if ($this->list !== null) {
            return $this->list;
        }
        try {
            return $this->list = Installation::isInstalled()
                ? Cache::rememberForever(self::CACHE_KEY, fn () => CodeSnippet::query()->where('is_active', true)->orderBy('sort')->orderBy('id')
                    ->get(['id', 'provider', 'tracking_id', 'code', 'placement', 'countries', 'skip_staff'])->map->toArray()->all())
                : [];
        } catch (Throwable) {
            return $this->list = [];
        }
    }

    /** Вставки, которые действуют для текущего посетителя. */
    public function active(): array
    {
        $country = country()?->code;
        $staff = (bool) auth()->user()?->isStaff();

        return array_values(array_filter($this->all(), fn (array $s) => ! ($s['skip_staff'] && $staff)
            && (empty($s['countries']) || ($country && in_array($country, $s['countries'], true)))
            && ($s['provider'] === 'custom' || self::validId($s['provider'], (string) $s['tracking_id']))));
    }

    public function render(string $placement): HtmlString
    {
        $out = [];
        $gtag = [];
        foreach ($this->active() as $s) {
            $id = (string) $s['tracking_id'];
            if ($s['provider'] === 'gtag') {
                if ($placement === 'head') {
                    $gtag[] = $id;
                }

                continue;
            }
            $part = match ($s['provider']) {
                'custom' => $s['placement'] === $placement ? (string) $s['code'] : null,
                'gtm' => self::gtm($id, $placement),
                'metrika' => self::metrika($id, $placement),
                'adsense' => $placement === 'head' ? '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.$id.'" crossorigin="anonymous"></script>' : null,
                default => null,
            };
            if ($part) {
                $out[] = $part;
            }
        }
        if ($gtag) { // один загрузчик gtag.js на все идентификаторы (GA4 + Google Ads)
            $gtag = array_values(array_unique($gtag));
            array_unshift($out, '<script async src="https://www.googletagmanager.com/gtag/js?id='.$gtag[0].'"></script>'
                ."<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());"
                .implode('', array_map(fn ($id) => "gtag('config','{$id}');", $gtag)).'</script>');
        }

        return new HtmlString($out ? "\n".implode("\n", $out)."\n" : '');
    }

    /** Строки ads.txt, которые следуют из вставок (AdSense → google.com, pub-…, DIRECT). */
    public function adsTxtLines(): array
    {
        return collect($this->all())->where('provider', 'adsense')->pluck('tracking_id')->filter(fn ($id) => self::validId('adsense', (string) $id))
            ->map(fn ($id) => 'google.com, '.substr($id, 3).', DIRECT, f08c47fec0942fa0')->unique()->values()->all();
    }

    public static function validId(string $provider, string $id): bool
    {
        return isset(CodeSnippet::ID_PATTERNS[$provider]) && preg_match(CodeSnippet::ID_PATTERNS[$provider], $id) === 1;
    }

    private static function gtm(string $id, string $placement): ?string
    {
        return match ($placement) {
            'head' => "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>",
            'body_start' => '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$id.'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>',
            default => null,
        };
    }

    private static function metrika(string $id, string $placement): ?string
    {
        return match ($placement) {
            'head' => "<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym({$id},'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});</script>",
            'body_start' => '<noscript><div><img src="https://mc.yandex.ru/watch/'.$id.'" style="position:absolute;left:-9999px" alt=""></div></noscript>',
            default => null,
        };
    }
}

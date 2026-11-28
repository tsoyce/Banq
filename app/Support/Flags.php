<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A46 · Флаги стран и валют. Библиотека — SVG flag-icons (MIT) в public/flags/{4x3,1x1}/{код}.svg,
 * локально, без CDN и без эмодзи (на Windows эмодзи-флаги показываются буквами «KZ»).
 * Свои флаги (замена библиотечного или новый код: регион, организация) — настройка flags.custom,
 * файлы — public/uploads/flags; правятся в админке «Сайт → Флаги». Вывод — partials/flag.
 */
final class Flags
{
    public const SETTING = 'flags.custom';

    public const DIR = 'uploads/flags';

    /** Библиотека: код → русское название. Коды — ISO 3166-1 alpha-2 в нижнем регистре и особые (eu, un, gb-sct…). */
    public const NAMES = [
        'ad' => 'Андорра', 'ae' => 'ОАЭ', 'af' => 'Афганистан', 'ag' => 'Антигуа и Барбуда', 'ai' => 'Ангилья', 
        'al' => 'Албания', 'am' => 'Армения', 'ao' => 'Ангола', 'aq' => 'Антарктида', 'ar' => 'Аргентина', 
        'arab' => 'Арабская лига', 'as' => 'Американское Самоа', 'asean' => 'АСЕАН', 'at' => 'Австрия', 'au' => 'Австралия', 
        'aw' => 'Аруба', 'ax' => 'Аландские о-ва', 'az' => 'Азербайджан', 'ba' => 'Босния и Герцеговина', 'bb' => 'Барбадос', 
        'bd' => 'Бангладеш', 'be' => 'Бельгия', 'bf' => 'Буркина-Фасо', 'bg' => 'Болгария', 'bh' => 'Бахрейн', 
        'bi' => 'Бурунди', 'bj' => 'Бенин', 'bl' => 'Сен-Бартелеми', 'bm' => 'Бермудские о-ва', 'bn' => 'Бруней', 
        'bo' => 'Боливия', 'bq' => 'Бонэйр, Синт-Эстатиус и Саба', 'br' => 'Бразилия', 'bs' => 'Багамы', 'bt' => 'Бутан', 
        'bv' => 'о-в Буве', 'bw' => 'Ботсвана', 'by' => 'Беларусь', 'bz' => 'Белиз', 'ca' => 'Канада', 
        'cc' => 'Кокосовые о-ва', 'cd' => 'Конго - Киншаса', 'cefta' => 'ЦЕФТА', 'cf' => 'Центрально-Африканская Республика', 'cg' => 'Конго - Браззавиль', 
        'ch' => 'Швейцария', 'ci' => 'Кот-д’Ивуар', 'ck' => 'о-ва Кука', 'cl' => 'Чили', 'cm' => 'Камерун', 
        'cn' => 'Китай', 'co' => 'Колумбия', 'cp' => 'Остров Клиппертон', 'cr' => 'Коста-Рика', 'cu' => 'Куба', 
        'cv' => 'Кабо-Верде', 'cw' => 'Кюрасао', 'cx' => 'о-в Рождества', 'cy' => 'Кипр', 'cz' => 'Чехия', 
        'de' => 'Германия', 'dg' => 'Диего-Гарсия', 'dj' => 'Джибути', 'dk' => 'Дания', 'dm' => 'Доминика', 
        'do' => 'Доминиканская Республика', 'dz' => 'Алжир', 'eac' => 'Восточноафриканское сообщество', 'ec' => 'Эквадор', 'ee' => 'Эстония', 
        'eg' => 'Египет', 'eh' => 'Западная Сахара', 'er' => 'Эритрея', 'es-ct' => 'Каталония', 'es-ga' => 'Галисия', 
        'es-pv' => 'Страна Басков', 'es' => 'Испания', 'et' => 'Эфиопия', 'eu' => 'Евросоюз', 'fi' => 'Финляндия', 
        'fj' => 'Фиджи', 'fk' => 'Фолклендские о-ва', 'fm' => 'Федеративные Штаты Микронезии', 'fo' => 'Фарерские о-ва', 'fr' => 'Франция', 
        'ga' => 'Габон', 'gb-eng' => 'Англия', 'gb-nir' => 'Северная Ирландия', 'gb-sct' => 'Шотландия', 'gb-wls' => 'Уэльс', 
        'gb' => 'Великобритания', 'gd' => 'Гренада', 'ge' => 'Грузия', 'gf' => 'Французская Гвиана', 'gg' => 'Гернси', 
        'gh' => 'Гана', 'gi' => 'Гибралтар', 'gl' => 'Гренландия', 'gm' => 'Гамбия', 'gn' => 'Гвинея', 
        'gp' => 'Гваделупа', 'gq' => 'Экваториальная Гвинея', 'gr' => 'Греция', 'gs' => 'Южная Георгия и Южные Сандвичевы о-ва', 'gt' => 'Гватемала', 
        'gu' => 'Гуам', 'gw' => 'Гвинея-Бисау', 'gy' => 'Гайана', 'hk' => 'Гонконг (САР)', 'hm' => 'о-ва Херд и Макдональд', 
        'hn' => 'Гондурас', 'hr' => 'Хорватия', 'ht' => 'Гаити', 'hu' => 'Венгрия', 'ic' => 'Канарские острова', 
        'id' => 'Индонезия', 'ie' => 'Ирландия', 'il' => 'Израиль', 'im' => 'о-в Мэн', 'in' => 'Индия', 
        'io' => 'Британская территория в Индийском океане', 'iq' => 'Ирак', 'ir' => 'Иран', 'is' => 'Исландия', 'it' => 'Италия', 
        'je' => 'Джерси', 'jm' => 'Ямайка', 'jo' => 'Иордания', 'jp' => 'Япония', 'ke' => 'Кения', 
        'kg' => 'Киргизия', 'kh' => 'Камбоджа', 'ki' => 'Кирибати', 'km' => 'Коморы', 'kn' => 'Сент-Китс и Невис', 
        'kp' => 'КНДР', 'kr' => 'Республика Корея', 'kw' => 'Кувейт', 'ky' => 'о-ва Кайман', 'kz' => 'Казахстан', 
        'la' => 'Лаос', 'lb' => 'Ливан', 'lc' => 'Сент-Люсия', 'li' => 'Лихтенштейн', 'lk' => 'Шри-Ланка', 
        'lr' => 'Либерия', 'ls' => 'Лесото', 'lt' => 'Литва', 'lu' => 'Люксембург', 'lv' => 'Латвия', 
        'ly' => 'Ливия', 'ma' => 'Марокко', 'mc' => 'Монако', 'md' => 'Молдова', 'me' => 'Черногория', 
        'mf' => 'Сен-Мартен', 'mg' => 'Мадагаскар', 'mh' => 'Маршалловы о-ва', 'mk' => 'Северная Македония', 'ml' => 'Мали', 
        'mm' => 'Мьянма (Бирма)', 'mn' => 'Монголия', 'mo' => 'Макао (САР)', 'mp' => 'Северные Марианские о-ва', 'mq' => 'Мартиника', 
        'mr' => 'Мавритания', 'ms' => 'Монтсеррат', 'mt' => 'Мальта', 'mu' => 'Маврикий', 'mv' => 'Мальдивы', 
        'mw' => 'Малави', 'mx' => 'Мексика', 'my' => 'Малайзия', 'mz' => 'Мозамбик', 'na' => 'Намибия', 
        'nc' => 'Новая Каледония', 'ne' => 'Нигер', 'nf' => 'о-в Норфолк', 'ng' => 'Нигерия', 'ni' => 'Никарагуа', 
        'nl' => 'Нидерланды', 'no' => 'Норвегия', 'np' => 'Непал', 'nr' => 'Науру', 'nu' => 'Ниуэ', 
        'nz' => 'Новая Зеландия', 'om' => 'Оман', 'pa' => 'Панама', 'pc' => 'Тихоокеанское сообщество', 'pe' => 'Перу', 
        'pf' => 'Французская Полинезия', 'pg' => 'Папуа — Новая Гвинея', 'ph' => 'Филиппины', 'pk' => 'Пакистан', 'pl' => 'Польша', 
        'pm' => 'Сен-Пьер и Микелон', 'pn' => 'о-ва Питкэрн', 'pr' => 'Пуэрто-Рико', 'ps' => 'Палестинские территории', 'pt' => 'Португалия', 
        'pw' => 'Палау', 'py' => 'Парагвай', 'qa' => 'Катар', 're' => 'Реюньон', 'ro' => 'Румыния', 
        'rs' => 'Сербия', 'ru' => 'Россия', 'rw' => 'Руанда', 'sa' => 'Саудовская Аравия', 'sb' => 'Соломоновы о-ва', 
        'sc' => 'Сейшельские о-ва', 'sd' => 'Судан', 'se' => 'Швеция', 'sg' => 'Сингапур', 'sh-ac' => 'Остров Вознесения', 
        'sh-hl' => 'Остров Святой Елены', 'sh-ta' => 'Тристан-да-Кунья', 'sh' => 'о-в Св. Елены', 'si' => 'Словения', 'sj' => 'Шпицберген и Ян-Майен', 
        'sk' => 'Словакия', 'sl' => 'Сьерра-Леоне', 'sm' => 'Сан-Марино', 'sn' => 'Сенегал', 'so' => 'Сомали', 
        'sr' => 'Суринам', 'ss' => 'Южный Судан', 'st' => 'Сан-Томе и Принсипи', 'sv' => 'Сальвадор', 'sx' => 'Синт-Мартен', 
        'sy' => 'Сирия', 'sz' => 'Эсватини', 'tc' => 'Тёркс и Кайкос', 'td' => 'Чад', 'tf' => 'Французские Южные территории', 
        'tg' => 'Того', 'th' => 'Таиланд', 'tj' => 'Таджикистан', 'tk' => 'Токелау', 'tl' => 'Восточный Тимор', 
        'tm' => 'Туркменистан', 'tn' => 'Тунис', 'to' => 'Тонга', 'tr' => 'Турция', 'tt' => 'Тринидад и Тобаго', 
        'tv' => 'Тувалу', 'tw' => 'Тайвань', 'tz' => 'Танзания', 'ua' => 'Украина', 'ug' => 'Уганда', 
        'um' => 'Внешние малые о-ва (США)', 'un' => 'ООН', 'us' => 'Соединенные Штаты', 'uy' => 'Уругвай', 'uz' => 'Узбекистан', 
        'va' => 'Ватикан', 'vc' => 'Сент-Винсент и Гренадины', 've' => 'Венесуэла', 'vg' => 'Виргинские о-ва (Великобритания)', 'vi' => 'Виргинские о-ва (США)', 
        'vn' => 'Вьетнам', 'vu' => 'Вануату', 'wf' => 'Уоллис и Футуна', 'ws' => 'Самоа', 'xk' => 'Косово', 
        'xx' => 'Неизвестный флаг', 'ye' => 'Йемен', 'yt' => 'Майотта', 'za' => 'Южно-Африканская Республика', 'zm' => 'Замбия', 
        'zw' => 'Зимбабве', 
    ];

    /** Валюты, чей код не начинается с кода страны-эмитента. Металлы и SDR (X…) — без флага. */
    private const CURRENCY = ['EUR' => 'eu', 'ANG' => 'cw', 'XCD' => 'ag', 'XAF' => 'cm', 'XOF' => 'sn', 'XPF' => 'pf'];

    /** «KZ», « kz », «GB-SCT» → «kz», «gb-sct»; мусор → null. */
    public static function normalize(?string $code): ?string
    {
        $c = Str::lower(trim((string) $code));

        return preg_match('/^[a-z]{2,8}(-[a-z0-9]{1,8})?$/', $c) ? $c : null;
    }

    /** Код флага по коду валюты: USD → us, EUR → eu, XAU → null. */
    public static function forCurrency(?string $currency): ?string
    {
        $cur = Str::upper(trim((string) $currency));
        if (isset(self::CURRENCY[$cur])) {
            return self::CURRENCY[$cur];
        }
        if (! preg_match('/^[A-W][A-Z]{2}$/', $cur)) {
            return null;
        }
        $cc = Str::lower(substr($cur, 0, 2));

        return self::has($cc) ? $cc : null;
    }

    /** Свои флаги: код → ['name' => …, 'url' => …]. */
    public static function custom(): array
    {
        $v = settings(self::SETTING, []);

        return is_array($v) ? $v : [];
    }

    public static function has(?string $code): bool
    {
        $c = self::normalize($code);

        return $c !== null && (isset(self::custom()[$c]['url']) || isset(self::NAMES[$c]));
    }

    /** Адрес картинки флага: свой (если загружен) → библиотечный → null. $shape: 4x3 (прямоугольник) или 1x1 (квадрат, для кружков). */
    public static function url(?string $code, string $shape = '4x3'): ?string
    {
        $c = self::normalize($code);
        if ($c === null) {
            return null;
        }
        if ($custom = self::custom()[$c]['url'] ?? null) {
            return asset(ltrim($custom, '/'));
        }

        return isset(self::NAMES[$c]) ? asset('flags/'.($shape === '1x1' ? '1x1' : '4x3').'/'.$c.'.svg') : null;
    }

    /**
     * HTML картинки флага (экранировано). Нет флага — пустая строка, или буквы кода с $fallback.
     * $round — кружок (квадратный вариант 1x1), иначе прямоугольник 4:3; размер — классами (по умолчанию 1em по высоте).
     */
    public static function img(?string $code, string $class = '', bool $round = false, bool $fallback = false, ?string $title = null): string
    {
        $url = self::url($code, $round ? '1x1' : '4x3');
        $cls = trim('flag'.($round ? ' flag-round' : '').' '.$class);
        $t = $title !== null ? ' title="'.e($title).'"' : '';
        if ($url === null) {
            return $fallback && $code ? '<span class="'.e($cls).' flag-none" aria-hidden="true"'.$t.'>'.e(Str::upper(substr((string) $code, 0, 2))).'</span>' : '';
        }

        return '<img src="'.e($url).'" alt="" class="'.e($cls).'" loading="lazy" decoding="async"'.$t.'>';
    }

    public static function name(?string $code): ?string
    {
        $c = self::normalize($code);

        return $c === null ? null : (self::custom()[$c]['name'] ?? self::NAMES[$c] ?? null);
    }

    /** Все флаги для админки: библиотека + свои; [код => ['name', 'url', 'custom' => bool, 'library' => bool]]. */
    public static function all(): array
    {
        $custom = self::custom();
        $all = [];
        foreach (self::NAMES + array_map(fn ($f) => $f['name'] ?? '', $custom) as $code => $name) {
            $all[$code] = [
                'name' => $custom[$code]['name'] ?? $name, 'url' => self::url((string) $code),
                'custom' => isset($custom[$code]['url']), 'library' => isset(self::NAMES[$code]),
            ];
        }
        $coll = class_exists(\Collator::class) ? new \Collator('ru_RU') : null;
        uasort($all, fn ($a, $b) => $coll ? $coll->compare($a['name'], $b['name']) : strcmp($a['name'], $b['name']));

        return $all;
    }

    /**
     * Проверить и сохранить загруженный флаг. SVG — только «чистый» (без скриптов, обработчиков, внешних ссылок):
     * открытый напрямую по адресу, он выполнялся бы на нашем домене. Растровые — по сигнатуре, не по расширению.
     */
    public static function store(UploadedFile $file, string $code): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $ext = match (true) {
            str_starts_with($raw, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($raw, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP' => 'webp',
            (bool) preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', $raw) => 'svg',
            default => throw new \InvalidArgumentException('Нужен SVG, PNG, JPG или WebP.'),
        };
        if ($ext === 'svg' && preg_match('/<script|<foreignObject|<!DOCTYPE|<!ENTITY|\son[a-z]+\s*=|javascript:|(?:xlink:)?href\s*=\s*["\']\s*(?!#)|<iframe|<embed|<object/i', $raw)) {
            throw new \InvalidArgumentException('В SVG есть скрипты, обработчики событий или внешние ссылки — такой файл не принимаем.');
        }
        if ($ext !== 'svg' && preg_match('/<\?php|<script/i', $raw)) {
            throw new \InvalidArgumentException('Внутри картинки найден код — файл отклонён.');
        }
        $name = $code.'-'.Str::lower(Str::random(6)).'.'.$ext;
        $dir = public_path(self::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.'/'.$name, $raw);

        return '/'.self::DIR.'/'.$name;
    }
}

<?php

namespace App\Support;

/**
 * A29 · Экранная типографика для русского текста (DESIGN §3.4).
 * Работает только с текстом между тегами: атрибуты, <script>, <style>, <textarea>, <pre>, <code> не трогает.
 *  - короткие предлоги, союзы и частицы приклеиваются к следующему слову (нет «висячих» «в», «и», «на» в конце строки);
 *  - тире приклеивается к предыдущему слову («таблица — лучшая», а не «таблица / — лучшая»);
 *  - число не отрывается от единицы: «12 мес», «10 млн», «27 ноября», «5 %», «1 000 ₸».
 */
class Typograph
{
    private const NBSP = "\u{00A0}";

    /** Слова длиной до трёх букв, которые не должны висеть в конце строки. */
    private const SHORT = 'в|во|и|с|со|к|ко|о|об|обо|у|а|я|на|по|за|из|от|до|не|ни|но|же|без|для|при|про|под|над|что|как|или';

    private const UNITS = 'мес\.?|месяц\w*|год\w*|лет|дн\w*|млн|млрд|тыс\.?|п\.п\.|₸|₽|\$|€|%|января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря|банк\w*|вклад\w*|ставк\w*|отзыв\w*';

    public static function html(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $skip = null; // имя тега, внутри которого текст не трогаем
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if ($skip !== null) {
                    if (preg_match('#^</'.$skip.'\b#i', $part)) {
                        $skip = null;
                    }
                } elseif (preg_match('#^<(script|style|textarea|pre|code)\b#i', $part, $m) && ! str_ends_with($part, '/>')) {
                    $skip = strtolower($m[1]);
                }

                continue;
            }
            if ($skip === null && trim($part) !== '') {
                $parts[$i] = self::text($part);
            }
        }

        return implode('', $parts);
    }

    public static function text(string $t): string
    {
        $nb = self::NBSP;
        // «слово — слово»: пробел перед тире → неразрывный (и &nbsp; в исходнике тоже уже неразрывный)
        $t = preg_replace('/[ \t]+(—|–)(?=\s)/u', $nb.'$1', $t);
        // короткое слово в начале текста или после пробела/скобки/кавычки → приклеить к следующему
        $t = preg_replace('/(^|[\s(«"„' . $nb . '])('.self::SHORT.')[ \t]+(?=\S)/iu', '$1$2'.$nb, $t);
        // второй проход: «и в банке» — соседние короткие слова перекрываются в одном проходе
        $t = preg_replace('/(^|[\s(«"„' . $nb . '])('.self::SHORT.')[ \t]+(?=\S)/iu', '$1$2'.$nb, $t);
        // число + единица
        $t = preg_replace('/(\d)[ \t]+('.self::UNITS.')(?![\p{L}])/u', '$1'.$nb.'$2', $t);

        return $t;
    }
}

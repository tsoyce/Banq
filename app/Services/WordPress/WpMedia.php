<?php

namespace App\Services\WordPress;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A43 · Скачивание и проверка файла с WordPress перед тем, как положить его к себе (public/uploads/blog/wp/ГГГГ/ММ).
 *  — тип определяется по первым байтам, а не по расширению: «картинка», которая на деле PHP или HTML, отклоняется;
 *  — в файле не должно быть `<?php`, `<script`, `<%` (полиглоты: JPEG с приклеенным кодом);
 *  — JPEG/PNG/WebP перекодируются через GD: так снимаются EXIF/GPS и всё, что приклеено после картинки;
 *    сторона больше MAX_SIDE уменьшается, PNG без прозрачности тяжелее 1 МБ становится JPEG;
 *  — GIF (анимация), PDF и MP3 копируются как есть после проверок.
 * Результат: ['ok' => true, 'path' => '/uploads/…', 'kind' => image|file, 'bytes' => n] или ['ok' => false, 'reason' => '…', 'danger' => bool].
 */
class WpMedia
{
    public const MAX_SIDE = 2400;

    public const MAX_BYTES = 40 * 1024 * 1024;

    public const DIR = 'uploads/blog/wp';

    /** Сигнатуры: тип → [расширение, функция проверки начала файла]. */
    private const MAGIC = [
        'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp', 'pdf' => 'pdf', 'mp3' => 'mp3',
    ];

    public function __construct(private int $timeout = 30) {}

    public static function sniff(string $bytes): ?string
    {
        $h = substr($bytes, 0, 16);

        return match (true) {
            str_starts_with($h, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($h, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($h, 'GIF87a') || str_starts_with($h, 'GIF89a') => 'gif',
            str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($h, '%PDF-') => 'pdf',
            str_starts_with($h, 'ID3') || (strlen($h) > 1 && ord($h[0]) === 0xFF && (ord($h[1]) & 0xE0) === 0xE0) => 'mp3',
            default => null,
        };
    }

    /** Признаки кода внутри файла (короткие сигнатуры вроде «<?=» случайно встречаются в двоичных данных — их не ищем). */
    public static function hasCode(string $bytes): ?string
    {
        foreach (['<?php' => 'PHP-код', '<script' => 'JavaScript', 'base64_decode(' => 'base64_decode()', 'eval(base64' => 'eval(base64…)'] as $needle => $label) {
            if (stripos($bytes, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    /** Скачать по адресу и сохранить. $hint — желаемое имя (по пути WordPress). */
    public function fetch(string $url, ?string $hint = null): array
    {
        try {
            $r = Http::timeout($this->timeout)->withUserAgent('BanqImporter/1.0 (+WordPress migration)')->withOptions(['allow_redirects' => ['max' => 3]])->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'не скачивается: '.Str::limit($e->getMessage(), 120), 'danger' => false];
        }
        if ($r->failed()) {
            return ['ok' => false, 'reason' => 'сервер ответил '.$r->status(), 'danger' => false];
        }

        return $this->store($r->body(), $hint ?? $url);
    }

    /** Проверить содержимое и сохранить в public/uploads/blog/wp. */
    public function store(string $bytes, string $nameFrom): array
    {
        if ($bytes === '') {
            return ['ok' => false, 'reason' => 'пустой файл', 'danger' => false];
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            return ['ok' => false, 'reason' => 'больше 40 МБ', 'danger' => false];
        }
        $declared = strtolower(pathinfo(parse_url($nameFrom, PHP_URL_PATH) ?: $nameFrom, PATHINFO_EXTENSION));
        $type = self::sniff($bytes);
        if (! $type) {
            $looksLikeCode = preg_match('/^\s*(<\?php|<\?=|<script|<html|<!doctype)/i', $bytes);

            return ['ok' => false, 'danger' => (bool) $looksLikeCode || in_array($declared, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                'reason' => $looksLikeCode ? 'внутри код, а не картинка (расширение .'.$declared.')' : 'тип файла не разрешён ('.($declared ?: 'без расширения').')'];
        }
        $expected = ['jpg' => 'jpeg', 'jpeg' => 'jpeg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp', 'pdf' => 'pdf', 'mp3' => 'mp3'][$declared] ?? null;
        if ($expected && $expected !== $type && ! (in_array($expected, ['jpeg', 'png', 'webp'], true) && in_array($type, ['jpeg', 'png', 'webp'], true))) {
            return ['ok' => false, 'reason' => 'расширение .'.$declared.' не совпадает с содержимым ('.$type.')', 'danger' => true];
        }
        if ($code = self::hasCode($bytes)) {
            return ['ok' => false, 'reason' => 'в файле найден '.$code, 'danger' => true];
        }

        $kind = in_array($type, ['jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'file';
        $ext = self::MAGIC[$type];
        $exifStripped = false;
        if (in_array($type, ['jpeg', 'png', 'webp'], true)) {
            $re = $this->reencode($bytes, $type);
            if ($re === null) {
                return ['ok' => false, 'reason' => 'картинка не открывается (битый файл)', 'danger' => false];
            }
            [$bytes, $ext] = $re;
            $exifStripped = true;
        } elseif ($type === 'gif' && @getimagesizefromstring($bytes) === false) {
            return ['ok' => false, 'reason' => 'GIF не открывается (битый файл)', 'danger' => false];
        }

        $path = $this->targetPath($nameFrom, $ext);
        $full = public_path(ltrim($path, '/'));
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0775, true);
        }
        file_put_contents($full, $bytes);

        return ['ok' => true, 'path' => $path, 'kind' => $kind, 'type' => $type, 'bytes' => strlen($bytes), 'clean' => $exifStripped];
    }

    /** Перекодировать через GD: без метаданных, с поворотом по EXIF, не больше MAX_SIDE. */
    private function reencode(string $bytes, string $type): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [$bytes, self::MAGIC[$type]]; // без GD — как есть (проверки выше уже пройдены)
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        if ($type === 'jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode(substr($bytes, 0, 256 * 1024)));
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle && ($rot = imagerotate($img, $angle, 0))) {
                imagedestroy($img);
                $img = $rot;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > self::MAX_SIDE) {
            $k = self::MAX_SIDE / max($w, $h);
            $scaled = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagecopyresampled($scaled, $img, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $w, $h);
            imagedestroy($img);
            $img = $scaled;
        }

        ob_start();
        if ($type === 'png') {
            imagesavealpha($img, true);
            imagepng($img, null, 8);
            $out = ob_get_clean();
            if (strlen($out) > 1024 * 1024 && ! $this->hasAlpha($img)) {
                ob_start();
                imagejpeg($img, null, 85);
                $out = ob_get_clean();
                $type = 'jpeg';
            }
        } elseif ($type === 'webp' && function_exists('imagewebp')) {
            imagewebp($img, null, 85);
            $out = ob_get_clean();
        } else {
            imageinterlace($img, true);
            imagejpeg($img, null, 85);
            $out = ob_get_clean();
            $type = 'jpeg';
        }
        imagedestroy($img);

        return [$out, self::MAGIC[$type]];
    }

    private function hasAlpha(\GdImage $img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) (max($w, $h) / 60));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** /uploads/blog/wp/2023/02/imya-fayla-1a2b3c.jpg — год/месяц из пути WordPress, имя латиницей, хвост от адреса. */
    private function targetPath(string $from, string $ext): string
    {
        $p = rawurldecode(parse_url($from, PHP_URL_PATH) ?: $from);
        preg_match('#/(\d{4})/(\d{2})/[^/]+$#', $p, $ym);
        $dir = $ym ? $ym[1].'/'.$ym[2] : 'other';
        $name = Str::limit(Str::slug(pathinfo($p, PATHINFO_FILENAME), '-', 'ru'), 80, '') ?: 'file';

        return '/'.self::DIR.'/'.$dir.'/'.$name.'-'.substr(sha1($from), 0, 6).'.'.$ext;
    }
}

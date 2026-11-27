<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A30 · Справочник платёжных систем и уровней карт. Вызывается из миграции 2026_11_27_003000 и повторно безопасен
 * (upsert по code / payment_system_id+code). Привилегии — типичный набор уровня; точные условия задаёт банк-эмитент.
 * Править и дополнять — в админке или через обмен данными (payment_systems, card_tiers).
 */
class PaymentSystemSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach (self::data() as $i => $ps) {
            $tiers = $ps['tiers'];
            unset($ps['tiers']);
            $row = array_merge(['short_name' => null, 'home_country' => null, 'countries' => null, 'color_2' => null, 'founded_year' => null, 'headquarters' => null, 'wallets' => null, 'meta' => null, 'aliases' => null], $ps);
            foreach (['countries', 'wallets', 'meta', 'aliases'] as $j) {
                $row[$j] = $row[$j] === null ? null : json_encode($row[$j], JSON_UNESCAPED_UNICODE);
            }
            $row += ['slug' => strtolower($ps['code']), 'is_active' => true, 'sort' => ($i + 1) * 10];

            $id = DB::table('payment_systems')->where('code', $ps['code'])->value('id');
            if ($id) {
                continue; // справочник уже есть — правки админа не трогаем
            }
            $id = DB::table('payment_systems')->insertGetId($row + ['created_at' => $now, 'updated_at' => $now]);

            foreach ($tiers as $j => [$code, $name, $level, $segment, $privileges]) {
                DB::table('card_tiers')->insert([
                    'payment_system_id' => $id, 'code' => $code, 'slug' => $row['slug'].'-'.str_replace('_', '-', $code), 'name' => $name,
                    'level' => $level, 'segment' => $segment, 'privileges' => json_encode($privileges, JSON_UNESCAPED_UNICODE),
                    'is_active' => true, 'sort' => ($j + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    private const BASE = ['Бесконтактная оплата', 'Оплата в интернете с 3-D Secure', 'Токенизация в мобильных кошельках'];

    private const GOLD = ['Расширенная поддержка держателя 24/7', 'Экстренная замена карты и выдача наличных за рубежом', 'Скидки партнёров платёжной системы'];

    private const PREMIUM = ['Консьерж-сервис', 'Доступ в бизнес-залы аэропортов (по программе банка)', 'Страхование путешествий (по программе банка)', 'Привилегии в отелях и ресторанах'];

    private const ELITE = ['Персональный консьерж 24/7', 'Неограниченные или расширенные проходы в бизнес-залы (по программе банка)', 'Расширенное страхование путешествий и покупок', 'Статусы в программах отелей и авиакомпаний', 'Высокие лимиты операций'];

    public static function data(): array
    {
        $b = self::BASE;
        $g = [...self::BASE, ...self::GOLD];
        $p = [...$g, ...self::PREMIUM];
        $e = [...$p, ...self::ELITE];
        $biz = [...self::BASE, 'Разделение личных и бизнес-расходов', 'Отчёты о расходах для бухгалтерии'];

        return [
            ['code' => 'VISA', 'name' => 'Visa', 'scope' => 'international', 'home_country' => 'US', 'color' => '#1A1F71', 'color_2' => '#2E5BFF', 'founded_year' => 1958,
                'headquarters' => 'Сан-Франциско, США', 'website' => 'https://www.visa.com', 'wallets' => ['apple_pay', 'google_pay', 'samsung_pay', 'garmin_pay'],
                'description' => 'Крупнейшая по числу карт международная платёжная система. Принимается почти в каждой стране мира.',
                'tiers' => [['classic', 'Visa Classic', 1, 'mass', $b], ['gold', 'Visa Gold', 2, 'mass', $g], ['platinum', 'Visa Platinum', 3, 'premium', $p],
                    ['signature', 'Visa Signature', 4, 'premium', $p], ['infinite', 'Visa Infinite', 5, 'elite', $e], ['business', 'Visa Business', 2, 'business', $biz]]],
            ['code' => 'MASTERCARD', 'name' => 'Mastercard', 'scope' => 'international', 'home_country' => 'US', 'color' => '#EB001B', 'color_2' => '#F79E1B', 'founded_year' => 1966,
                'headquarters' => 'Перчейз, Нью-Йорк, США', 'website' => 'https://www.mastercard.com', 'wallets' => ['apple_pay', 'google_pay', 'samsung_pay', 'garmin_pay'], 'meta' => ['mark' => 'MC'],
                'description' => 'Вторая крупнейшая международная платёжная система; программа привилегий Priceless.',
                'tiers' => [['standard', 'Mastercard Standard', 1, 'mass', $b], ['gold', 'Mastercard Gold', 2, 'mass', $g], ['platinum', 'Mastercard Platinum', 3, 'premium', $p],
                    ['world', 'Mastercard World', 3, 'premium', $p], ['world_elite', 'Mastercard World Elite', 5, 'elite', $e], ['business', 'Mastercard Business', 2, 'business', $biz]]],
            ['code' => 'UNIONPAY', 'name' => 'UnionPay', 'short_name' => 'UnionPay', 'scope' => 'international', 'home_country' => 'CN', 'color' => '#E21836', 'color_2' => '#00447C', 'founded_year' => 2002,
                'headquarters' => 'Шанхай, Китай', 'website' => 'https://www.unionpayintl.com', 'wallets' => ['apple_pay', 'huawei_pay'], 'meta' => ['mark' => 'UP'], 'aliases' => ['China UnionPay', 'Юнионпэй'],
                'description' => 'Китайская платёжная система, крупнейшая в мире по объёму операций; удобна для поездок и покупок в Китае.',
                'tiers' => [['classic', 'UnionPay Classic', 1, 'mass', $b], ['gold', 'UnionPay Gold', 2, 'mass', $g], ['platinum', 'UnionPay Platinum', 3, 'premium', $p], ['diamond', 'UnionPay Diamond', 5, 'elite', $e]]],
            ['code' => 'MIR', 'name' => 'Мир', 'scope' => 'national', 'home_country' => 'RU', 'countries' => ['RU'], 'color' => '#0F754E', 'color_2' => '#00B4E6', 'founded_year' => 2015,
                'headquarters' => 'Москва, Россия', 'website' => 'https://mironline.ru', 'wallets' => ['mir_pay'], 'aliases' => ['Mir'],
                'description' => 'Национальная платёжная система России (оператор — НСПК).',
                'tiers' => [['classic', 'Мир Классическая', 1, 'mass', $b], ['privilege', 'Мир Привилегия', 4, 'premium', $p], ['privilege_plus', 'Мир Привилегия Плюс', 5, 'elite', $e]]],
            ['code' => 'AMEX', 'name' => 'American Express', 'short_name' => 'Amex', 'scope' => 'international', 'home_country' => 'US', 'color' => '#006FCF', 'color_2' => '#00A3E0',
                'headquarters' => 'Нью-Йорк, США', 'website' => 'https://www.americanexpress.com', 'wallets' => ['apple_pay', 'google_pay'], 'meta' => ['mark' => 'AMEX'],
                'description' => 'Международная система с упором на премиальный сегмент и программу Membership Rewards.',
                'tiers' => [['green', 'American Express Green', 2, 'mass', $g], ['gold', 'American Express Gold', 3, 'premium', $p], ['platinum', 'American Express Platinum', 5, 'elite', $e], ['centurion', 'American Express Centurion', 6, 'elite', $e]]],
            ['code' => 'JCB', 'name' => 'JCB', 'scope' => 'international', 'home_country' => 'JP', 'color' => '#0B4EA2', 'color_2' => '#00A94F', 'founded_year' => 1961,
                'headquarters' => 'Токио, Япония', 'website' => 'https://www.global.jcb', 'wallets' => ['apple_pay', 'google_pay'],
                'description' => 'Японская международная платёжная система, сильна в Азии.',
                'tiers' => [['standard', 'JCB Standard', 1, 'mass', $b], ['gold', 'JCB Gold', 3, 'premium', $g], ['platinum', 'JCB Platinum', 4, 'premium', $p], ['the_class', 'JCB The Class', 6, 'elite', $e]]],
            ['code' => 'ELCART', 'name' => 'Элкарт', 'scope' => 'national', 'home_country' => 'KG', 'countries' => ['KG'], 'color' => '#0055A5', 'color_2' => '#E30613',
                'headquarters' => 'Бишкек, Кыргызстан', 'meta' => ['mark' => 'ЭЛК'], 'aliases' => ['Elcart'],
                'description' => 'Национальная платёжная система Кыргызстана.',
                'tiers' => [['classic', 'Элкарт', 1, 'mass', $b]]],
            ['code' => 'HUMO', 'name' => 'Humo', 'scope' => 'national', 'home_country' => 'UZ', 'countries' => ['UZ'], 'color' => '#00A859', 'color_2' => '#0B3D2E',
                'headquarters' => 'Ташкент, Узбекистан', 'aliases' => ['Хумо'],
                'description' => 'Национальная платёжная система Узбекистана.',
                'tiers' => [['classic', 'Humo', 1, 'mass', $b]]],
            ['code' => 'UZCARD', 'name' => 'Uzcard', 'scope' => 'national', 'home_country' => 'UZ', 'countries' => ['UZ'], 'color' => '#1B4F9C', 'color_2' => '#0FB5AE',
                'headquarters' => 'Ташкент, Узбекистан', 'meta' => ['mark' => 'UZC'], 'aliases' => ['Узкард'],
                'description' => 'Межбанковская платёжная система Узбекистана.',
                'tiers' => [['classic', 'Uzcard', 1, 'mass', $b]]],
            ['code' => 'BELKART', 'name' => 'БЕЛКАРТ', 'short_name' => 'Белкарт', 'scope' => 'national', 'home_country' => 'BY', 'countries' => ['BY'], 'color' => '#C8102E', 'color_2' => '#007A33',
                'headquarters' => 'Минск, Беларусь', 'meta' => ['mark' => 'БК'], 'aliases' => ['Belkart'],
                'description' => 'Национальная платёжная система Беларуси.',
                'tiers' => [['classic', 'БЕЛКАРТ', 1, 'mass', $b], ['premium', 'БЕЛКАРТ-Премиум', 3, 'premium', $p]]],
        ];
    }
}

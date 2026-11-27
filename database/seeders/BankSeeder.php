<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * A06 · Стартовый справочник банков РК.
 * ЧЕРНОВИК: состав, коды и названия сверить с реестром АРРФР перед запуском.
 * Код — то, что пишется в Excel (BCC, FFIN …).
 */
class BankSeeder extends Seeder
{
    public const BANKS = [
        ['code' => 'HSBK', 'aliases' => ['Halyk', 'Халык', 'Народный банк'], 'name' => 'Народный Банк Казахстана', 'short_name' => 'Halyk Bank', 'brand_color' => '#00805F', 'website' => 'https://halykbank.kz'],
        ['code' => 'KASPI', 'aliases' => ['Каспи'], 'name' => 'Kaspi Bank', 'short_name' => 'Kaspi', 'brand_color' => '#F14635', 'website' => 'https://kaspi.kz'],
        ['code' => 'BCC', 'aliases' => ['ЦентрКредит', 'БЦК', 'Bank CenterCredit'], 'name' => 'Банк ЦентрКредит', 'short_name' => 'BCC', 'brand_color' => '#1E8C4E', 'website' => 'https://www.bcc.kz'],
        ['code' => 'FFIN', 'aliases' => ['Freedom', 'Фридом'], 'name' => 'Freedom Bank Kazakhstan', 'short_name' => 'Freedom Bank', 'brand_color' => '#3CB44A', 'website' => 'https://bankffin.kz'],
        ['code' => 'FORTE', 'aliases' => ['Forte', 'Форте'], 'name' => 'ForteBank', 'short_name' => 'ForteBank', 'brand_color' => '#A6005B', 'website' => 'https://forte.kz'],
        ['code' => 'JUSAN', 'aliases' => ['Жусан'], 'name' => 'Jusan Bank', 'short_name' => 'Jusan', 'brand_color' => '#FF6B00', 'website' => 'https://jusan.kz'],
        ['code' => 'EUBANK', 'aliases' => ['Eurasian', 'Евразийский', 'EUB'], 'name' => 'Евразийский банк', 'short_name' => 'Eurasian Bank', 'brand_color' => '#C8102E', 'website' => 'https://eubank.kz'],
        ['code' => 'BEREKE', 'aliases' => ['Береке'], 'name' => 'Bereke Bank', 'short_name' => 'Bereke', 'brand_color' => '#00A19A', 'website' => 'https://berekebank.kz'],
        ['code' => 'OTBASY', 'aliases' => ['Отбасы', 'ЖССБ'], 'name' => 'Отбасы банк', 'short_name' => 'Otbasy', 'brand_color' => '#0067B1', 'website' => 'https://hcsbk.kz'],
        ['code' => 'HCB', 'aliases' => ['Homecredit', 'Хоум Кредит', 'HCSB'], 'name' => 'Home Credit Bank', 'short_name' => 'Home Credit', 'brand_color' => '#E30613', 'website' => 'https://home.kz'],
        ['code' => 'RBK', 'aliases' => ['РБК'], 'name' => 'Bank RBK', 'short_name' => 'RBK', 'brand_color' => '#5A2D82', 'website' => 'https://bankrbk.kz'],
        ['code' => 'NURBANK', 'aliases' => ['Нурбанк'], 'name' => 'Нурбанк', 'short_name' => 'Nurbank', 'brand_color' => '#003F7F', 'website' => 'https://nurbank.kz'],
        ['code' => 'SHINHAN', 'aliases' => ['Шинхан'], 'name' => 'Шинхан Банк Казахстан', 'short_name' => 'Shinhan', 'brand_color' => '#0046FF', 'website' => 'https://shinhan.kz'],
        ['code' => 'ALTYN', 'aliases' => ['Altyn', 'Altyn-i', 'Алтын'], 'name' => 'Altyn Bank', 'short_name' => 'Altyn Bank', 'brand_color' => '#C9A13B', 'website' => 'https://altynbank.kz'],
        ['code' => 'KZI', 'name' => 'KZI Bank', 'short_name' => 'KZI Bank', 'brand_color' => '#1B365D', 'website' => 'https://kzibank.kz'],
        ['code' => 'BOC', 'name' => 'Bank of China Kazakhstan', 'short_name' => 'Bank of China', 'brand_color' => '#B5121B', 'website' => 'https://www.bankofchina.com/kz'],
        ['code' => 'ICBC', 'name' => 'Торгово-промышленный банк Китая в г. Алматы', 'short_name' => 'ICBC', 'brand_color' => '#C7000B', 'website' => 'https://www.icbc.com.cn'],
        ['code' => 'CITI', 'name' => 'Ситибанк Казахстан', 'short_name' => 'Citibank', 'brand_color' => '#003B70', 'website' => 'https://www.citibank.kz'],
    ];

    public function run(): void
    {
        // A21 · справочник — банки Казахстана (страна KZ создаётся миграцией и CountrySeeder)
        $kz = Country::query()->where('code', 'KZ')->value('id');
        foreach (self::BANKS as $i => $bank) {
            Bank::updateOrCreate(['country_id' => $kz, 'code' => $bank['code']], $bank + ['sort' => ($i + 1) * 10]);
        }
    }
}

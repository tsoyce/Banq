<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A21 · Мультистрановость: страна — отдельная сущность.
 * Всё «национальное» (валюта, регулятор, центробанк, система гарантирования вкладов и её лимиты)
 * живёт в стране, а не в коде. Банк принадлежит стране; продукты, ставки и рейтинги — через банк.
 * Будущие разделы (карты, кредиты, пакеты) берут страну так же — через bank_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();               // ISO 3166-1 alpha-2: KZ, UZ, KG…
            $table->string('slug', 32)->unique();               // в URL: ?country=kz
            $table->string('name');                             // Казахстан
            $table->string('name_genitive')->nullable();        // Казахстана — «банки Казахстана»
            $table->string('name_locative')->nullable();        // в Казахстане
            $table->string('name_en')->nullable();
            $table->string('name_native')->nullable();          // Қазақстан
            $table->string('flag', 16)->nullable();             // эмодзи 🇰🇿
            $table->string('currency', 3);                      // ISO 4217 основной валюты: KZT
            $table->string('currency_symbol', 8)->nullable();   // ₸
            $table->json('currencies')->nullable();             // валюты вкладов по порядку: ["KZT","USD","EUR"]
            $table->string('locale', 10)->default('ru');
            $table->string('timezone', 64)->nullable();
            $table->string('phone_code', 8)->nullable();
            $table->json('regulator')->nullable();              // {short, name, url}
            $table->json('central_bank')->nullable();           // {short, name, url, base_rate}
            $table->json('deposit_guarantee')->nullable();      // {short, name, url, limits: {savings, term, demand, foreign}}
            $table->json('meta')->nullable();                   // всё прочее, что появится у страны
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        $kz = DB::table('countries')->insertGetId([
            'code' => 'KZ', 'slug' => 'kz', 'name' => 'Казахстан', 'name_genitive' => 'Казахстана', 'name_locative' => 'в Казахстане',
            'name_en' => 'Kazakhstan', 'name_native' => 'Қазақстан', 'flag' => '🇰🇿', 'currency' => 'KZT', 'currency_symbol' => '₸',
            'currencies' => json_encode(['KZT', 'USD', 'EUR', 'RUB', 'CNY']), 'locale' => 'ru', 'timezone' => 'Asia/Almaty', 'phone_code' => '+7',
            'regulator' => json_encode(['short' => 'АРРФР', 'name' => 'Агентство РК по регулированию и развитию финансового рынка', 'url' => 'https://www.gov.kz/memleket/entities/ardfm'], JSON_UNESCAPED_UNICODE),
            'central_bank' => json_encode(['short' => 'НБРК', 'name' => 'Национальный Банк Казахстана', 'url' => 'https://nationalbank.kz'], JSON_UNESCAPED_UNICODE),
            'deposit_guarantee' => json_encode(['short' => 'КФГД', 'name' => 'Казахстанский фонд гарантирования депозитов', 'url' => 'https://kdif.kz',
                'limits' => ['savings' => 20_000_000, 'term' => 10_000_000, 'demand' => 10_000_000, 'foreign' => 5_000_000]], JSON_UNESCAPED_UNICODE),
            'is_active' => true, 'sort' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
        DB::table('banks')->update(['country_id' => $kz]);

        Schema::table('banks', function (Blueprint $table) {
            // код банка уникален внутри страны (у двух стран может быть свой «BCC»)
            $table->dropUnique(['code']);
            $table->unique(['country_id', 'code']);
            // участие в системе гарантирования вкладов своей страны (было is_kdif_member)
            $table->renameColumn('is_kdif_member', 'deposit_insured');
        });
    }

    public function down(): void
    {
        // сначала внешний ключ: в MySQL он опирается на уникальный индекс (country_id, code)
        Schema::table('banks', fn (Blueprint $table) => $table->dropForeign(['country_id']));
        Schema::table('banks', function (Blueprint $table) {
            $table->renameColumn('deposit_insured', 'is_kdif_member');
            $table->dropUnique(['country_id', 'code']);
            $table->unique(['code']);
            $table->dropColumn('country_id');
        });
        Schema::dropIfExists('countries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A50 · Коды сторонних сервисов (Google Analytics / Ads / AdSense, Tag Manager, Яндекс Метрика, свой HTML).
 *   Прежнее поле «Счётчики и метрики» (settings site.counters) переносится сюда одной вставкой «Свой код» в <head>.
 * A51 · Своя реклама: места (ad_slots) → кампании (ad_campaigns) → объявления (ad_creatives), статистика по дням (ad_stats).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_snippets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('provider', 20)->default('custom');      // custom | gtag | gtm | metrika | adsense
            $table->string('tracking_id', 60)->nullable();            // G-XXXX / AW-123 / GTM-XXXX / 12345678 / ca-pub-…
            $table->text('code')->nullable();                         // свой HTML (provider = custom)
            $table->string('placement', 12)->default('head');         // head | body_start | body_end
            $table->json('countries')->nullable();                    // ISO-коды; пусто — все страны
            $table->boolean('skip_staff')->default(true);             // не грузить для вошедших сотрудников
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('ad_slots', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();                      // home-wide, sidebar, feed-native, article-inline
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->json('formats')->nullable();                      // image | native | html
            $table->unsignedSmallInteger('width')->default(1200);     // пропорции резерва места (без сдвига вёрстки)
            $table->unsignedSmallInteger('height')->default(200);
            $table->unsignedSmallInteger('m_width')->nullable();      // на телефоне (≤ 640px)
            $table->unsignedSmallInteger('m_height')->nullable();
            $table->text('fallback_code')->nullable();                // если своих объявлений нет — код сети (AdSense), грузится лениво
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('advertiser', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('countries')->nullable();                    // пусто — все страны
            $table->json('slots')->nullable();                        // ключи мест; пусто — все подходящие по формату
            $table->unsignedTinyInteger('priority')->default(5);      // 1–10, вес при выборе
            $table->unsignedSmallInteger('freq_cap')->default(3);     // показов одному посетителю в день; 0 — без ограничения
            $table->unsignedInteger('max_impressions')->nullable();   // бюджет показов
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->string('type', 10)->default('native');            // image | native | html
            $table->string('name', 160);
            $table->string('title', 160)->nullable();
            $table->string('body', 300)->nullable();
            $table->string('cta', 40)->nullable();
            $table->string('image', 255)->nullable();
            $table->string('image_mobile', 255)->nullable();
            $table->string('alt', 160)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('sponsor', 120)->nullable();               // «Реклама · Halyk Bank»
            $table->string('disclosure', 255)->nullable();            // маркировка: erid, ИНН/БИН рекламодателя
            $table->text('html')->nullable();                         // type = html — в изолированном iframe
            $table->unsignedTinyInteger('weight')->default(5);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('creative_id')->constrained('ad_creatives')->cascadeOnDelete();
            $table->string('slot', 40);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['date', 'creative_id', 'slot']);
            $table->index(['campaign_id', 'date']);
        });

        // Места по умолчанию: где они стоят в шаблонах — см. docs/REGISTRY.md §12
        $now = now();
        DB::table('ad_slots')->insert([
            ['key' => 'home-wide', 'name' => 'Главная — широкий баннер', 'description' => 'Между «Топом ставок» и рейтингами', 'formats' => json_encode(['image', 'native', 'html']), 'width' => 1200, 'height' => 200, 'm_width' => 640, 'm_height' => 320, 'sort' => 10],
            ['key' => 'sidebar', 'name' => 'Правая колонка', 'description' => 'Главная (под курсами валют), лента журнала, статья', 'formats' => json_encode(['image', 'native', 'html']), 'width' => 300, 'height' => 250, 'm_width' => null, 'm_height' => null, 'sort' => 20],
            ['key' => 'feed-native', 'name' => 'Лента журнала — нативная карточка', 'description' => 'Карточкой среди публикаций, на первой странице ленты', 'formats' => json_encode(['native']), 'width' => 320, 'height' => 360, 'm_width' => null, 'm_height' => null, 'sort' => 30],
            ['key' => 'article-inline', 'name' => 'Внутри статьи', 'description' => 'После трети текста длинной статьи, иначе — в конце', 'formats' => json_encode(['image', 'native', 'html']), 'width' => 728, 'height' => 180, 'm_width' => 640, 'm_height' => 320, 'sort' => 40],
        ]);
        DB::table('ad_slots')->update(['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        // Прежнее поле «Счётчики и метрики» → вставка «Свой код» в <head>
        $old = DB::table('settings')->where('key', 'site.counters')->value('value');
        $old = $old !== null ? json_decode($old, true) : null;
        if (is_string($old) && trim($old) !== '') {
            DB::table('code_snippets')->insert(['name' => 'Счётчики (из настроек)', 'provider' => 'custom', 'code' => $old, 'placement' => 'head', 'skip_staff' => false, 'is_active' => true, 'sort' => 100, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('settings')->where('key', 'site.counters')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('code_snippets');
    }
};

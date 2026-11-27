<?php

use Database\Seeders\PaymentSystemSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A30 · Раздел «Карты»: платёжные системы и их уровни карт (международные, без страны),
 * банковские карты (дебетовые, кредитные, предоплаченные) и пакеты услуг — страна через bank_id.
 * Справочник платёжных систем и уровней заполняется здесь же (PaymentSystemSeeder) — как KZ в A21.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_systems', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();                 // VISA, MASTERCARD, UNIONPAY, MIR…
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('short_name', 80)->nullable();
            $table->json('aliases')->nullable();                    // как ещё пишут: «China UnionPay», «Элкарт» — для импорта
            $table->string('scope', 16)->default('international'); // international | regional | national
            $table->string('home_country', 2)->nullable();          // ISO-2 страны происхождения (без FK — у страны может не быть банков на портале)
            $table->json('countries')->nullable();                  // ISO-2, где система национальная/основная: ["KG"]
            $table->string('color', 9)->nullable();                 // фирменный цвет
            $table->string('color_2', 9)->nullable();               // второй цвет градиента
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('headquarters')->nullable();
            $table->json('wallets')->nullable();                     // apple_pay, google_pay, samsung_pay…
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('card_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_system_id')->constrained()->cascadeOnDelete();
            $table->string('code', 48);                              // classic, gold, platinum, infinite…
            $table->string('slug', 120)->unique();                   // visa-infinite
            $table->string('name');                                  // Visa Infinite
            $table->unsignedTinyInteger('level')->default(1);        // 1 — базовый … 6 — топ; для сравнения и сортировки
            $table->string('segment', 16)->default('mass');          // mass | premium | elite | business | virtual
            $table->text('description')->nullable();
            $table->json('privileges')->nullable();                  // список привилегий уровня
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
            $table->unique(['payment_system_id', 'code']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->index();                     // debit | credit | prepaid
            $table->string('name');
            $table->string('slug', 160)->unique();
            $table->string('segment', 16)->nullable();               // mass | premium | elite | business | youth | kids
            $table->string('format', 16)->default('physical');       // physical | virtual | both
            $table->json('currencies')->nullable();
            // обслуживание (в нацвалюте страны банка)
            $table->decimal('issue_fee', 14, 2)->nullable();
            $table->decimal('annual_fee', 14, 2)->nullable();
            $table->decimal('monthly_fee', 14, 2)->nullable();
            $table->string('fee_note')->nullable();                  // «бесплатно при тратах от 100 000 ₸»
            // кэшбэк и остаток
            $table->decimal('cashback_max', 6, 2)->nullable();       // %
            $table->decimal('cashback_base', 6, 2)->nullable();      // % на всё
            $table->string('cashback_note')->nullable();
            $table->decimal('balance_rate', 6, 2)->nullable();       // % на остаток
            // кредит
            $table->decimal('credit_limit_max', 16, 2)->nullable();
            $table->unsignedSmallInteger('grace_days')->nullable();
            $table->decimal('credit_rate', 6, 2)->nullable();        // номинальная, % годовых
            $table->decimal('credit_rate_effective', 6, 2)->nullable(); // ГЭСВ
            $table->decimal('min_payment', 5, 2)->nullable();        // % от долга
            // снятие и переводы
            $table->string('cash_fee')->nullable();
            $table->decimal('free_cash_limit', 14, 2)->nullable();   // бесплатное снятие в месяц
            $table->string('transfer_fee')->nullable();
            // выпуск
            $table->boolean('online_opening')->default(true);
            $table->boolean('delivery')->default(false);
            $table->unsignedTinyInteger('validity_years')->nullable();
            $table->unsignedTinyInteger('age_from')->nullable();
            $table->json('features')->nullable();                    // contactless, apple_pay, multicurrency, installments, metal…
            $table->string('color', 9)->nullable();                  // лицевая сторона карты: градиент color → color_2
            $table->string('color_2', 9)->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        // Карта выпускается в одной или нескольких системах/уровнях: «Visa Gold или Mastercard Gold»
        Schema::create('card_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['card_id', 'payment_system_id', 'card_tier_id']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 160)->unique();
            $table->string('segment', 16)->default('mass');          // mass | premium | private | business
            $table->decimal('monthly_fee', 14, 2)->nullable();
            $table->decimal('annual_fee', 14, 2)->nullable();
            $table->string('fee_note')->nullable();                  // условие бесплатного обслуживания
            $table->json('perks')->nullable();                       // список привилегий
            $table->json('conditions')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 9)->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('card_service_package', function (Blueprint $table) {
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_package_id', 'card_id']);
        });

        (new PaymentSystemSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('card_service_package');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('card_networks');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('card_tiers');
        Schema::dropIfExists('payment_systems');
    }
};

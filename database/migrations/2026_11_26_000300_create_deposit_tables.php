<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A07 · Депозиты: продукты, история ставок, журнал импортов.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32)->default('deposits');
            $table->string('filename');
            $table->string('path')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('log')->nullable();
            $table->string('status', 16)->default('done');
            $table->timestamps();
        });

        Schema::create('deposit_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type', 16)->index();            // App\Enums\DepositType
            $table->json('currencies')->nullable();         // ["KZT","USD"]
            $table->decimal('min_amount', 16, 2)->nullable();
            $table->decimal('max_amount', 16, 2)->nullable();
            $table->unsignedSmallInteger('term_min')->nullable(); // месяцы
            $table->unsignedSmallInteger('term_max')->nullable();
            $table->string('capitalization', 16)->nullable();     // monthly | end | none
            $table->boolean('can_replenish')->default(false);
            $table->boolean('can_withdraw')->default(false);
            $table->boolean('online_opening')->default(true);
            $table->text('description')->nullable();
            $table->json('conditions')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('deposit_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_product_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('KZT');
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->decimal('amount_from', 16, 2)->nullable();
            $table->decimal('amount_to', 16, 2)->nullable();
            $table->decimal('rate', 6, 3);                  // номинальная, % годовых
            $table->decimal('effective_rate', 6, 3)->nullable(); // ГЭСВ
            $table->date('valid_from');
            $table->date('valid_to')->nullable();           // null = действует сейчас
            $table->string('source', 16)->default('manual'); // manual | import | parser
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['deposit_product_id', 'currency', 'term_months', 'valid_to'], 'deposit_rates_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_rates');
        Schema::dropIfExists('deposit_products');
        Schema::dropIfExists('imports');
    }
};

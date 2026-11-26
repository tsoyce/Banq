<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A18 · Рейтинги банков: три вида рядом (авторский, объективный, народный) + отзывы с модерацией.
 * ratings — история оценок по дням (одна запись на банк × вид × дату, старые не перезаписываются).
 * reviews — отзывы пользователей, review_votes — «полезно».
 * banks — данные для объективного рейтинга (активы, капитал, лицензия).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->decimal('assets_bn', 12, 1)->nullable()->after('founded_year');   // активы, млрд ₸ (АРРФР)
            $table->decimal('capital_bn', 12, 1)->nullable()->after('assets_bn');     // собственный капитал, млрд ₸
            $table->date('financials_at')->nullable()->after('capital_bn');           // на какую дату цифры
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);                       // author | objective | people
            $table->decimal('score', 4, 2);                    // 0.00–5.00
            $table->json('breakdown')->nullable();            // критерии объективного / распределение народного
            $table->unsignedInteger('votes')->nullable();     // число отзывов (народный)
            $table->text('verdict')->nullable();              // авторский: вывод
            $table->json('pros')->nullable();                 // авторский: плюсы
            $table->json('cons')->nullable();                 // авторский: минусы
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period');                           // дата оценки / снимка
            $table->timestamps();
            $table->unique(['bank_id', 'kind', 'period']);
            $table->index(['kind', 'period']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deposit_product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('score');             // 1–5
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 16)->default('pending')->index(); // pending | approved | rejected
            $table->string('reject_reason')->nullable();
            $table->text('reply')->nullable();                // официальный ответ банка / редакции
            $table->string('reply_author')->nullable();
            $table->unsignedInteger('helpful')->default(0);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'bank_id']);           // один отзыв на банк; правка возвращает на модерацию
            $table->index(['bank_id', 'status']);
        });

        Schema::create('review_votes', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('ratings');
        Schema::table('banks', fn (Blueprint $table) => $table->dropColumn(['assets_bn', 'capital_bn', 'financials_at']));
    }
};

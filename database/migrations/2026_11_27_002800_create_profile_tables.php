<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A28 · Профиль пользователя: публичный адрес /u/{username}, аватар, город и страна, «о себе»,
 * настройки уведомлений; избранное (вклады, банки) и сохранённые сравнения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->unique()->after('name');   // /u/{username}
            $table->string('avatar')->nullable()->after('email');                 // uploads/avatars/…
            $table->string('city', 80)->nullable();
            $table->char('country_code', 2)->nullable();                          // ISO 3166-1 alpha-2, не FK: страны — отдельный модуль
            $table->string('bio', 280)->nullable();
            $table->boolean('is_public')->default(true);                          // показывать профиль и ссылку у отзывов
            $table->json('notify')->nullable();                                   // {review_status, review_reply, rate_changes}
            $table->timestamp('last_seen_at')->nullable();
        });

        // Уже зарегистрированным — адрес из имени
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $u) {
            $base = Str::limit(Str::slug($u->name, '-', 'ru'), 30, '') ?: 'user';
            $name = in_array($base, $taken, true) || strlen($base) < 3 ? $base.'-'.$u->id : $base;
            $taken[] = $name;
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        }

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favoritable_type', 20);                               // deposit | bank (morph map)
            $table->unsignedBigInteger('favoritable_id');
            $table->timestamps();
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id']);
            $table->index(['favoritable_type', 'favoritable_id']);
        });

        Schema::create('saved_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->json('product_ids');
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_comparisons');
        Schema::dropIfExists('favorites');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar', 'city', 'country_code', 'bio', 'is_public', 'notify', 'last_seen_at']);
        });
    }
};

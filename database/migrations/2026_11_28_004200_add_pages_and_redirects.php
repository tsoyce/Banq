<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A42 · Статические страницы и редиректы.
 *  — posts.type: post (публикация ленты) | page (страница «О проекте», «Оферта»…). Страницы живут по адресу /{slug},
 *    в ленту не попадают, пока не включено in_feed. nav — место в меню сайта (aside | footer), nav_sort — порядок.
 *  — redirects: старый адрес → новый (301), с подсчётом переходов. Источник wp — перенос с WordPress (A43).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('type', 10)->default('post')->after('id');
            $table->boolean('in_feed')->default(false)->after('type');
            $table->string('nav', 10)->nullable()->after('in_feed');
            $table->unsignedSmallInteger('nav_sort')->default(500)->after('nav');
            $table->index(['type', 'status']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500);                       // нормализованный путь без хоста: /halyk-2025, /?p=2506
            $table->string('from_hash', 64)->unique();              // sha256(from_path) — уникальность длинных путей в MySQL
            $table->string('to_url', 500);                          // /blog/halyk-2025 или https://…
            $table->unsignedSmallInteger('status')->default(301);
            $table->string('source', 20)->nullable();               // wp | manual
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn(['type', 'in_feed', 'nav', 'nav_sort']);
        });
    }
};

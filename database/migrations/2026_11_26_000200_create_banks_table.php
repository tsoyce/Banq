<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A06 · Справочник банков РК.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('code', 32)->unique();          // BCC, FFIN … — ключ для импорта Excel
            $table->string('name');
            $table->string('name_kz')->nullable();
            $table->string('short_name')->nullable();
            $table->json('aliases')->nullable();               // как банк пишут в Excel: Halyk, Forte, Homecredit…
            $table->string('legal_name')->nullable();
            $table->string('bin', 12)->nullable();
            $table->string('license')->nullable();
            $table->string('swift', 11)->nullable();
            $table->string('logo')->nullable();
            $table->string('brand_color', 9)->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 32)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->boolean('is_kdif_member')->default(true);   // участник КФГД
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active')->index(); // active | revoked | merged
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};

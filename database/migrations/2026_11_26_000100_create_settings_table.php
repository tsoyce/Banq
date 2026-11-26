<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A05 · Настройки сайта (ключ → значение). Читаются через App\Support\Settings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 32)->default('general')->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('user')->after('password')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A46 · Флаги и страны сотрудников.
 * countries.flag_code — код флага из библиотеки/своих (пусто — по ISO-коду страны);
 * users.admin_countries — id стран, в которых работает сотрудник (null — все страны).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('flag_code', 16)->nullable()->after('flag');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->json('admin_countries')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('flag_code'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('admin_countries'));
    }
};

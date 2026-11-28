<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A53 · Сохранённые сравнения любого вида: вклады (как было), карты, пакеты, банки, платёжные системы.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_comparisons', function (Blueprint $table) {
            $table->string('type', 32)->default('deposits')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('saved_comparisons', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

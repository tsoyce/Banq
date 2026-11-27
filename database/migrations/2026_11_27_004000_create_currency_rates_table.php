<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A40 · Официальные курсы валют центробанка страны (НБРК для KZ).
 * Одна строка — курс валюты на дату: история копится сама, прошлые дни не перезаписываются.
 * Курс — за `nominal` единиц (у НБРК 10 драм, 1000 донгов), в нацвалюте страны.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);                    // ISO 4217: USD
            $table->string('name')->nullable();                // как назвал источник
            $table->unsignedInteger('nominal')->default(1);    // за сколько единиц курс
            $table->decimal('rate', 18, 6);                    // курс за nominal единиц
            $table->decimal('change', 18, 6)->nullable();      // изменение к прошлому курсу по данным источника
            $table->date('date');                              // дата, на которую установлен курс
            $table->string('source', 32);                      // ключ провайдера: nbrk, cbu, cbr
            $table->timestamps();

            $table->unique(['country_id', 'currency', 'date']);
            $table->index(['country_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A54 · Карты физлицам и бизнесу: cards.audience (personal | business).
 * Уровни карт: свой значок (badge — загруженный SVG/PNG) и подпись на значке по умолчанию (badge_text, например «MIR Privilege»).
 * Платёжные системы: второй вариант знака (logo_alt — латиница/кириллица, монохром).
 *
 * Перенос данных: карта «для бизнеса» — та, у которой сегмент был business или есть бизнес-уровень (Visa Business…);
 * сегмент business у карты больше не используется (он стал аудиторией) — обнуляется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('audience', 16)->default('personal')->after('kind')->index();
        });
        Schema::table('card_tiers', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('privileges');
            $table->string('badge_text', 60)->nullable()->after('badge');
        });
        Schema::table('payment_systems', function (Blueprint $table) {
            $table->string('logo_alt')->nullable()->after('logo');
        });

        $businessTiers = DB::table('card_tiers')->where('segment', 'business')->pluck('id');
        $ids = DB::table('cards')->where('segment', 'business')->pluck('id')
            ->merge(DB::table('card_networks')->whereIn('card_tier_id', $businessTiers)->pluck('card_id'))->unique();
        if ($ids->isNotEmpty()) {
            DB::table('cards')->whereIn('id', $ids)->update(['audience' => 'business']);
        }
        DB::table('cards')->where('segment', 'business')->update(['segment' => null]);
    }

    public function down(): void
    {
        DB::table('cards')->where('audience', 'business')->whereNull('segment')->update(['segment' => 'business']);
        Schema::table('cards', function (Blueprint $t) {
            $t->dropIndex(['audience']);
            $t->dropColumn('audience');
        });
        Schema::table('card_tiers', fn (Blueprint $t) => $t->dropColumn(['badge', 'badge_text']));
        Schema::table('payment_systems', fn (Blueprint $t) => $t->dropColumn('logo_alt'));
    }
};

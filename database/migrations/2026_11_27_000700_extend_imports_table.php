<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A22 · История импортов для универсального обмена: формат, источник (admin/cli/api), режим, ошибки, счётчики по сущностям. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->foreignId('api_token_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('format', 16)->nullable()->after('filename');   // json | csv | xlsx | matrix
            $table->string('source', 16)->default('admin')->after('format'); // admin | cli | api
            $table->string('mode', 16)->nullable()->after('source');       // upsert | create | update
            $table->unsignedInteger('rows_failed')->default(0)->after('rows_skipped');
            $table->json('stats')->nullable()->after('rows_failed');       // {banks: {created, updated, …}, …}
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_token_id');
            $table->dropColumn(['format', 'source', 'mode', 'rows_failed', 'stats']);
        });
    }
};

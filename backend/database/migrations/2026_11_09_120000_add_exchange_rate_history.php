<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FX audit trail (ARCHITECTURE D21): every fetched rate is kept (append-only, never edited),
 * and each order records the market rate, markup and rate snapshot behind its locked rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_history', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('currency', 3);
            $table->decimal('rate', 24, 12); // market rate, no markup
            $table->string('source', 32);
            $table->timestamp('fetched_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['base_currency', 'currency', 'fetched_at']);
            $table->index('fetched_at');
        });

        // Today's stored rates become the first history entries.
        DB::table('exchange_rate_history')->insertUsing(
            ['base_currency', 'currency', 'rate', 'source', 'fetched_at', 'created_at'],
            DB::table('exchange_rates')->select(['base_currency', 'currency', 'rate', 'source', 'fetched_at', 'created_at']),
        );

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('market_rate', 24, 12)->nullable()->after('exchange_rate');
            $table->unsignedSmallInteger('exchange_markup_bps')->nullable()->after('market_rate');
            $table->timestamp('exchange_rate_fetched_at')->nullable()->after('exchange_markup_bps');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['market_rate', 'exchange_markup_bps', 'exchange_rate_fetched_at']));
        Schema::dropIfExists('exchange_rate_history');
    }
};

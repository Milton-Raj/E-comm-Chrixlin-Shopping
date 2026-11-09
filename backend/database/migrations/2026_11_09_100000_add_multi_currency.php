<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * International selling (ARCHITECTURE D21): daily exchange rates, the shopper's chosen
 * currency on the bag, and the currency/rate an order was charged in. Every amount column
 * on orders stays in the store currency; the charged figure sits beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('currency', 3);
            $table->decimal('rate', 24, 12); // 1 unit of base = rate units of currency (market, no markup)
            $table->string('source', 32);
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['base_currency', 'currency']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->char('display_currency', 3)->nullable()->after('currency');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Null when the order was charged in the store currency.
            $table->char('presentment_currency', 3)->nullable()->after('currency');
            $table->decimal('exchange_rate', 24, 12)->nullable()->after('presentment_currency'); // includes the markup
            $table->bigInteger('presentment_total')->nullable()->after('exchange_rate');
        });

        Schema::table('refunds', function (Blueprint $table) {
            // What the gateway returned to the customer, in the currency they paid.
            $table->bigInteger('gateway_amount')->nullable()->after('currency');
            $table->char('gateway_currency', 3)->nullable()->after('gateway_amount');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn(['gateway_amount', 'gateway_currency']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['presentment_currency', 'exchange_rate', 'presentment_total']));
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('display_currency'));
        Schema::dropIfExists('exchange_rates');
    }
};

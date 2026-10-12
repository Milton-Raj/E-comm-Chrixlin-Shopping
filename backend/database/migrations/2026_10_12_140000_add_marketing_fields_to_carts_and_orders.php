<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Abandoned-bag reminder tracking and paid gift wrapping with a gift message. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('last_activity_at');
            $table->boolean('gift_wrap')->default(false)->after('coupon_code');
            $table->string('gift_message', 300)->nullable()->after('gift_wrap');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('gift_wrap_total')->default(0)->after('shipping_total'); // GST-inclusive
            $table->bigInteger('gift_wrap_tax')->default(0)->after('gift_wrap_total');
            $table->string('gift_message', 300)->nullable()->after('gift_wrap_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['gift_wrap_total', 'gift_wrap_tax', 'gift_message']));
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn(['reminder_sent_at', 'gift_wrap', 'gift_message']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Courier-booked shipments (Shiprocket): provider ids, booking state and the latest courier status. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('order_id'); // null = recorded manually
            $table->string('provider_order_id', 64)->nullable()->after('provider');
            $table->string('provider_shipment_id', 64)->nullable()->after('provider_order_id');
            $table->string('status', 32)->nullable()->after('tracking_url'); // booking | pickup_scheduled | failed | in_transit | delivered | exception
            $table->string('courier_status', 64)->nullable()->after('status');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('courier_status');
            $table->text('last_error')->nullable()->after('pickup_scheduled_at');
            $table->timestamp('last_event_at')->nullable()->after('last_error');

            $table->index(['provider', 'tracking_number']);
            $table->index(['provider', 'provider_shipment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['provider', 'tracking_number']);
            $table->dropIndex(['provider', 'provider_shipment_id']);
            $table->dropColumn(['provider', 'provider_order_id', 'provider_shipment_id', 'status', 'courier_status', 'pickup_scheduled_at', 'last_error', 'last_event_at']);
        });
    }
};

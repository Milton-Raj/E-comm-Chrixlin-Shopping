<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cart, wishlist, shipping, coupons, orders, payments, shipments (DATABASE.md §3.4–§3.9).
 * Orders, payments, transactions, refunds and usages are never soft-deleted (§67).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('code', 32);
            $table->string('zone', 16); // domestic | international
            $table->string('name');
            $table->string('description')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('free_over')->nullable();
            $table->unsignedSmallInteger('days_min');
            $table->unsignedSmallInteger('days_max');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['code', 'zone']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->string('type', 32); // percentage | fixed | free_shipping
            $table->bigInteger('value')->default(0); // bps for percentage, minor units for fixed
            $table->bigInteger('max_discount')->nullable();
            $table->bigInteger('min_order_total')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active'); // active | merged | converted | abandoned
            $table->char('currency', 3);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('shipping_method_code', 32)->nullable();
            $table->string('coupon_code', 64)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_seen')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price_at_add');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_value')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('status', 32);
            $table->string('payment_status', 32);
            $table->string('fulfillment_status', 32);
            $table->char('currency', 3);
            $table->bigInteger('subtotal');
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('shipping_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total');
            $table->bigInteger('refunded_total')->default(0);
            $table->boolean('prices_include_tax')->default(true);
            $table->json('tax_breakdown')->nullable();
            $table->string('coupon_code', 64)->nullable();
            $table->json('shipping_method')->nullable();
            $table->boolean('requires_attention')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['status', 'placed_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('email');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_type', 32);
            $table->string('sku');
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('image_path')->nullable();
            $table->bigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->bigInteger('line_subtotal');
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('line_total');
            $table->boolean('requires_shipping');
            $table->unsignedInteger('refunded_quantity')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 16); // shipping | billing
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state_code', 8);
            $table->string('postal_code', 16);
            $table->char('country_code', 2);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 16); // active | committed | released | expired
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('order_id');
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->bigInteger('discount_amount');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'email']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_order_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('method', 32)->nullable();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('failure_message')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id']);
            $table->index(['provider', 'provider_order_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('type', 32); // capture | failure | refund
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->string('provider_transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['payment_id', 'type', 'provider_transaction_id'], 'payment_tx_idempotency');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('reason');
            $table->string('status', 16);
            $table->string('provider_refund_id')->nullable()->unique();
            $table->boolean('restock')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id');
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->string('status', 16); // received | processed | failed | ignored
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('scope', 128);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['key', 'scope']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        foreach (['shipments', 'idempotency_keys', 'webhook_events', 'refunds', 'payment_transactions', 'payments', 'coupon_usages',
            'inventory_reservations', 'order_access_tokens', 'order_status_history', 'order_addresses', 'order_items', 'orders',
            'order_number_sequences', 'wishlist_items', 'cart_items', 'carts', 'coupons', 'shipping_methods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GST tax invoices: one per paid order, numbered consecutively within each Indian
 * financial year (April–March). Invoices are financial records and are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->string('financial_year', 7)->primary(); // e.g. 2026-27
            $table->unsignedInteger('last_value')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 16)->unique(); // GST rule 46: at most 16 characters
            $table->string('financial_year', 7);
            $table->unsignedInteger('sequence');
            $table->json('seller'); // seller details as they were when the invoice was issued
            $table->timestamp('issued_at');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_year', 'sequence']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // GST rate applied at checkout, in basis points (1800 = 18%). Null on older orders.
            $table->unsignedSmallInteger('tax_rate_bps')->nullable()->after('tax_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('tax_rate_bps'));
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};

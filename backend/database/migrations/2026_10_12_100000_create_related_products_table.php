<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hand-picked "Pairs well with" products (DATABASE.md §3.2 related_products). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('related_products', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 20)->default('cross_sell');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['product_id', 'related_product_id', 'type']);
            $table->index('related_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_products');
    }
};

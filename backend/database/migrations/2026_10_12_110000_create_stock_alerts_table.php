<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Email me when it's back" requests; one pending request per variant and email. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['variant_id', 'email']);
            $table->index(['notified_at', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};

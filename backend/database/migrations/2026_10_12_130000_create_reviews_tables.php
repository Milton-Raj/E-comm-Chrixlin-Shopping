<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer reviews with photos (DATABASE.md §3.11). Shown only after approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete(); // set ⇒ Verified purchase
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('review_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_photos');
        Schema::dropIfExists('reviews');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Homepage hero slideshow, edited in Admin → Content (DATABASE.md §3.14). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('image_path')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->string('focal_point', 20)->default('50% 50%');
            $table->string('eyebrow', 80)->nullable();
            $table->string('title', 120);
            $table->string('body', 300)->nullable();
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};

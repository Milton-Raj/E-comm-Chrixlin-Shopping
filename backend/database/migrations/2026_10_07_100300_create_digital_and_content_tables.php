<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Digital delivery (DATABASE.md §3.10) and CMS pages (§3.14). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('download_limit')->nullable();
            $table->unsignedSmallInteger('access_days')->nullable();
            $table->string('format')->nullable();
            $table->timestamps();
        });

        Schema::create('digital_files', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('digital_entitlements', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('download_limit')->nullable();
            $table->unsignedInteger('downloads_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 16); // available | downloaded | refunded | revoked
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('order_id');
        });

        Schema::create('download_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained('digital_entitlements')->cascadeOnDelete();
            $table->foreignId('digital_file_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
        });

        Schema::create('digital_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained('digital_entitlements')->restrictOnDelete();
            $table->foreignId('digital_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16); // completed | denied
            $table->string('deny_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitlement_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('status', 16)->default('draft'); // draft | published
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['pages', 'digital_downloads', 'download_tokens', 'digital_entitlements', 'digital_files', 'digital_products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

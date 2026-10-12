<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Corporate & bulk gifting enquiries from the storefront form, worked in Admin → Enquiries. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('type', 30)->default('corporate_gifting');
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('budget', 60)->nullable();
            $table->string('occasion', 80)->nullable();
            $table->date('needed_by')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};

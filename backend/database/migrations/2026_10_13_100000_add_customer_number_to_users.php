<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Readable customer numbers (CUS-000001) for admin URLs, from a locked sequence row like order numbers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_value')->default(0);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_number', 20)->nullable()->unique()->after('uuid');
        });

        $value = 0;
        DB::table('users')->orderBy('id')->select('id')->chunkById(500, function ($users) use (&$value) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['customer_number' => sprintf('CUS-%06d', ++$value)]);
            }
        });
        DB::table('customer_number_sequences')->insert(['id' => 1, 'last_value' => $value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['customer_number']);
            $table->dropColumn('customer_number');
        });
        Schema::dropIfExists('customer_number_sequences');
    }
};

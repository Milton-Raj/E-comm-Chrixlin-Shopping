<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Product galleries can hold YouTube videos next to images (type = youtube, no file on disk). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->string('type', 16)->default('image')->after('product_id');
            $table->string('video_id', 32)->nullable()->after('path');
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('product_media')->where('type', '!=', 'image')->delete();
        Schema::table('product_media', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
            $table->dropColumn(['type', 'video_id']);
        });
    }
};

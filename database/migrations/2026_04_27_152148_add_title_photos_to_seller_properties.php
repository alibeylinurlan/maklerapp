<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_properties', function (Blueprint $table) {
            $table->string('title')->nullable()->after('seller_id');
            $table->json('photos')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('seller_properties', function (Blueprint $table) {
            $table->dropColumn(['title', 'photos']);
        });
    }
};

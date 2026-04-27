<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // property_id nullable et
        DB::statement('ALTER TABLE price_history MODIFY property_id BIGINT UNSIGNED NULL');

        Schema::table('price_history', function (Blueprint $table) {
            $table->foreignId('seller_property_id')->nullable()->after('property_id')
                  ->constrained('seller_properties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_history', function (Blueprint $table) {
            $table->dropForeign(['seller_property_id']);
            $table->dropColumn('seller_property_id');
        });
        DB::statement('ALTER TABLE price_history MODIFY property_id BIGINT UNSIGNED NOT NULL');
    }
};

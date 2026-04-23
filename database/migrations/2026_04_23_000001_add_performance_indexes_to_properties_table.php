<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // is_owner + bumped_at — əsas filter kombinasiyası
            $table->index(['is_owner', 'bumped_at'], 'properties_is_owner_bumped_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_is_owner_bumped_at_index');
        });
    }
};

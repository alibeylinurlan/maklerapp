<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_matches', function (Blueprint $table) {
            $table->index(['customer_request_id', 'status']);
            $table->index('dismissed_at');
            $table->index('created_at');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->index(['is_owner', 'is_business']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('property_matches', function (Blueprint $table) {
            $table->dropIndex(['customer_request_id', 'status']);
            $table->dropIndex(['dismissed_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['is_owner', 'is_business']);
            $table->dropIndex(['updated_at']);
        });
    }
};

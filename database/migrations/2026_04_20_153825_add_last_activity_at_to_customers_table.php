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
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('updated_at')->index();
        });

        // Mövcud müştərilər üçün updated_at ilə doldur
        DB::statement('UPDATE customers SET last_activity_at = updated_at');
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};

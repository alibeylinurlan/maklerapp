<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_lists', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('share_token');
            $table->index('last_activity_at');
        });

        DB::statement('UPDATE saved_lists SET last_activity_at = updated_at');
    }

    public function down(): void
    {
        Schema::table('saved_lists', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};

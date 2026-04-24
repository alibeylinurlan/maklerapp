<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sessions', function (Blueprint $table) {
            $table->integer('logged_in_at')->nullable()->after('last_activity');
        });
    }
    public function down(): void {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('logged_in_at');
        });
    }
};

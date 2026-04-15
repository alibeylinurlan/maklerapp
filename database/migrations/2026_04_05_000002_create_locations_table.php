<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('bina_id', 10);
            $table->string('slug', 100);
            $table->string('name_az', 150);
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bina_id']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

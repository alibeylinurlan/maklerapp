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
        Schema::create('saved_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_shared')->default(false); // gələcəkdə ortaq baxış üçün
            $table->string('share_token')->nullable()->unique(); // paylaşma linki üçün
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('saved_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['saved_list_id', 'property_id']);
            $table->index('saved_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_list_items');
        Schema::dropIfExists('saved_lists');
    }
};

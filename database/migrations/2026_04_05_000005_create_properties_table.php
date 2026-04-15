<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('bina_id', 20)->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 5)->default('AZN');
            $table->decimal('area', 8, 2)->nullable();
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->string('floor', 20)->nullable();
            $table->string('location_full_name')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->json('photos')->nullable();
            $table->boolean('has_mortgage')->default(false);
            $table->boolean('has_repair')->default(false);
            $table->boolean('is_business')->default(false);
            $table->boolean('is_owner')->nullable();
            $table->timestamp('owner_checked_at')->nullable();
            $table->timestamp('bumped_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'rooms', 'price']);
            $table->index(['location_id']);
            $table->index(['bumped_at']);
            $table->index(['is_owner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

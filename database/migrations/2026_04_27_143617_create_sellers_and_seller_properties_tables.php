<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('seller_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->default('AZN');
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->decimal('area', 8, 2)->nullable();
            $table->unsignedSmallInteger('floor')->nullable();
            $table->unsignedSmallInteger('floor_total')->nullable();
            $table->text('notes')->nullable();
            $table->string('bina_url')->nullable();
            $table->string('source', 20)->default('manual'); // manual | bina_link
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_properties');
        Schema::dropIfExists('sellers');
    }
};

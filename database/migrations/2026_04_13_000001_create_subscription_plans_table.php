<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->comment('Unikal açar (platform, customer_requests, matches ...)');
            $table->string('name_az', 100)->comment('Azərbaycanca adı');
            $table->text('description_az')->nullable()->comment('Açıqlama');
            $table->decimal('price', 8, 2)->default(0)->comment('Aylıq qiymət (AZN)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plans');
        Schema::dropIfExists('subscription_plans');
    }
};

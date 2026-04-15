<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('has_bill_of_sale')->default(false)->after('has_repair')->comment('Çıxarışı var/yox');
            $table->boolean('is_leased')->default(false)->after('has_bill_of_sale')->comment('Kirayə/satış (true=kirayə)');
            $table->unsignedSmallInteger('floor_total')->nullable()->after('floor')->comment('Binanın ümumi mərtəbə sayı');
            $table->boolean('is_vipped')->default(false)->after('is_business')->comment('VIP elan');
            $table->boolean('is_featured')->default(false)->after('is_vipped')->comment('Premium/seçilmiş elan');
        });

        // Mövcud column-lara comment əlavə et
        Schema::table('properties', function (Blueprint $table) {
            $table->string('bina_id', 20)->comment('bina.az elan ID')->change();
            $table->decimal('price', 12, 2)->nullable()->comment('Qiymət')->change();
            $table->string('currency', 5)->default('AZN')->comment('Valyuta (AZN, USD, EUR)')->change();
            $table->decimal('area', 8, 2)->nullable()->comment('Sahə (m²)')->change();
            $table->unsignedTinyInteger('rooms')->nullable()->comment('Otaq sayı')->change();
            $table->string('floor', 20)->nullable()->comment('Mərtəbə (məs: 5)')->change();
            $table->string('location_full_name')->nullable()->comment('Ərazi tam adı (bina.az-dan)')->change();
            $table->string('path')->comment('bina.az relative URL (/items/123)')->change();
            $table->boolean('has_mortgage')->default(false)->comment('İpoteka var/yox')->change();
            $table->boolean('has_repair')->default(false)->comment('Təmirli/təmirsiz')->change();
            $table->boolean('is_business')->default(false)->comment('Şirkət/biznes elanı')->change();
            $table->boolean('is_owner')->nullable()->comment('Mülkiyyətçi (true=sahibkar, false=vasitəçi)')->change();
            $table->timestamp('owner_checked_at')->nullable()->comment('Mülkiyyətçi yoxlama tarixi')->change();
            $table->timestamp('bumped_at')->nullable()->comment('bina.az-da yenilənmə tarixi')->change();
            $table->timestamp('first_seen_at')->nullable()->comment('Bizim ilk gördüyümüz tarix')->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['has_bill_of_sale', 'is_leased', 'floor_total', 'is_vipped', 'is_featured']);
        });
    }
};

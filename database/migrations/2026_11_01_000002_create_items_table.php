<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 100)->unique();
            $table->string('name', 200)->index();
            $table->string('category', 50)->index(); // RAW_RESIN, MASTERBATCH, PACKAGING, FINISHED_GOOD
            $table->string('unit_of_measure', 20)->default('KG');
            $table->decimal('standard_pack_qty', 10, 2)->default(1.00);
            $table->decimal('minimum_safety_stock', 10, 2)->default(0.00);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

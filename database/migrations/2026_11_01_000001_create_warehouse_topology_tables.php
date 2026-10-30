<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 30)->index();
            $table->string('name', 100);
            $table->string('zone_type', 40)->index(); // RAW_MATERIAL, FINISHED_GOODS, STAGING_BUFFER, QUARANTINE
            $table->boolean('is_temperature_controlled')->default(false);
            $table->timestamps();
        });

        Schema::create('warehouse_bins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('warehouse_zones')->cascadeOnDelete();
            $table->string('bin_code', 50)->unique(); // Scannable Barcode, e.g. RM-A-01-03
            $table->string('aisle', 10)->index();
            $table->string('rack', 10);
            $table->string('shelf', 10);
            $table->string('bin', 10);
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_volume_cbm', 10, 2)->nullable();
            $table->boolean('is_blocked')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_zones');
        Schema::dropIfExists('warehouses');
    }
};

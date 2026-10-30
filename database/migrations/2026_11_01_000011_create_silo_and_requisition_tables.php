<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('silos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->constrained('warehouse_zones')->cascadeOnDelete();
            $table->foreignUuid('bin_id')->constrained('warehouse_bins')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('code', 50)->unique(); // e.g. SILO-PP-01
            $table->string('name', 150);
            $table->decimal('total_height_m', 8, 2);
            $table->decimal('diameter_m', 8, 2);
            $table->decimal('cone_height_m', 8, 2)->default(0);
            $table->decimal('sensor_deadband_m', 8, 2)->default(0);
            $table->decimal('bulk_density_kg_m3', 8, 2); // e.g. 520 kg/m3 for PP
            $table->decimal('max_capacity_kg', 12, 2);
            $table->decimal('reorder_threshold_kg', 12, 2);
            $table->decimal('critical_threshold_kg', 12, 2);
            $table->decimal('current_distance_m', 8, 2)->nullable();
            $table->decimal('current_weight_kg', 12, 2)->default(0);
            $table->decimal('current_fill_percentage', 5, 2)->default(0);
            $table->string('status', 30)->default('NORMAL')->index(); // NORMAL, WARNING, CRITICAL, OFFLINE
            $table->timestamp('last_telemetry_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('silo_telemetry_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('silo_id')->constrained('silos')->cascadeOnDelete();
            $table->string('sensor_type', 50)->default('RADAR_80GHZ');
            $table->decimal('distance_m', 8, 2);
            $table->decimal('material_height_m', 8, 2);
            $table->decimal('volume_m3', 10, 3);
            $table->decimal('weight_kg', 12, 2);
            $table->decimal('fill_percentage', 5, 2);
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('battery_percentage', 5, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pr_number', 50)->unique();
            $table->foreignUuid('silo_id')->nullable()->constrained('silos')->nullOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('requested_by_type', 40)->default('SYSTEM_AUTO_REORDER'); // SYSTEM_AUTO_REORDER, MANUAL_OPERATOR
            $table->decimal('requested_quantity_kg', 12, 2);
            $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, SUBMITTED, APPROVED, ORDERED, FULFILLED, CANCELLED
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('silo_telemetry_readings');
        Schema::dropIfExists('silos');
    }
};

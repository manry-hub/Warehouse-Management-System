<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_quarantines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quarantine_code', 50)->unique();
            $table->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('trigger_lot_id')->constrained('material_lots')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->string('severity', 30)->default('MAJOR'); // CRITICAL, MAJOR, MINOR
            $table->integer('impacted_lots_count')->default(0);
            $table->decimal('impacted_total_quantity', 12, 3)->default(0.000);
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, RESOLVED, SCRAPPED
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hopper_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_code', 50)->unique();
            $table->string('machine_code', 50)->index();
            $table->foreignUuid('hopper_bin_id')->constrained('warehouse_bins');
            $table->foreignUuid('material_lot_id')->constrained('material_lots');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('production_order_number', 50)->index();
            $table->decimal('quantity_staged_kg', 10, 3);
            $table->decimal('quantity_returned_kg', 10, 3)->default(0.000);
            $table->string('status', 30)->default('STAGED'); // STAGED, CONSUMING, CLOSED
            $table->timestamp('staged_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pallet_containers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sscc_code', 20)->unique();
            $table->foreignUuid('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->integer('carton_count')->default(0);
            $table->decimal('gross_weight_kg', 10, 3)->default(0.000);
            $table->decimal('tare_weight_kg', 10, 3)->default(15.000);
            $table->decimal('net_weight_kg', 10, 3)->default(0.000);
            $table->string('status', 30)->default('OPEN'); // OPEN, SEALED, STAGED, LOADED
            $table->timestamps();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('gate_pass_number', 50)->nullable()->unique();
            $table->foreignId('security_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seal_number', 50)->nullable();
            $table->mediumText('driver_signature')->nullable();
            $table->timestamp('gate_out_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['security_officer_id']);
            $table->dropColumn([
                'gate_pass_number',
                'security_officer_id',
                'seal_number',
                'driver_signature',
                'gate_out_at',
            ]);
        });

        Schema::dropIfExists('pallet_containers');
        Schema::dropIfExists('hopper_transactions');
        Schema::dropIfExists('quality_quarantines');
    }
};

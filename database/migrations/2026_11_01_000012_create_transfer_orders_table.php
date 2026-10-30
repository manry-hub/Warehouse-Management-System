<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 50)->unique();
            $table->foreignUuid('origin_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('carrier_name', 100)->nullable();
            $table->string('vehicle_plate_number', 50)->nullable();
            $table->string('waybill_number', 50)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_order_id')->constrained('transfer_orders')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->cascadeOnDelete();
            $table->foreignUuid('origin_bin_id')->constrained('warehouse_bins')->cascadeOnDelete();
            $table->foreignUuid('destination_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->boolean('is_cross_docked')->default(false)->index();
            $table->foreignUuid('cross_dock_delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_order_items');
        Schema::dropIfExists('transfer_orders');
    }
};

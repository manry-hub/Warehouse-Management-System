<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('do_number', 50)->unique();
            $table->string('customer_name', 200)->index();
            $table->text('shipping_address');
            $table->string('vehicle_plate_number', 30);
            $table->string('driver_name', 150);
            $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, PICKING, LOADED, DISPATCHED, CANCELLED
            $table->integer('total_cartons')->default(0);
            $table->timestamp('dispatched_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->string('carton_barcode', 100)->nullable()->index();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_loaded', 12, 3)->default(0);
            $table->boolean('is_verified_scanned')->default(false);
            $table->foreignUuid('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');
    }
};

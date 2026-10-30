<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rmas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rma_number', 50)->unique();
            $table->string('customer_name', 100);
            $table->foreignUuid('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->string('status', 30)->default('PENDING_RECEIPT')->index(); // PENDING_RECEIPT, RECEIVED, UNDER_INSPECTION, RESOLVED, CANCELLED
            $table->string('disposition', 30)->default('PENDING')->index(); // PENDING, RESTOCK, SCRAP_REGRIND, SCRAP_DISPOSAL
            $table->integer('total_cartons')->default(0);
            $table->integer('received_cartons')->default(0);
            $table->string('defect_reason', 255);
            $table->text('qc_inspection_notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->string('credit_memo_number', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_rma_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_rma_id')->constrained('customer_rmas')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUuid('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->string('carton_barcode', 100)->nullable()->index();
            $table->decimal('quantity_returned', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0.000);
            $table->string('disposition', 30)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('supplier_rtvs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rtv_number', 50)->unique();
            $table->string('vendor_code', 50);
            $table->string('vendor_name', 100);
            $table->string('po_number', 50)->nullable();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->restrictOnDelete();
            $table->foreignUuid('warehouse_bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $table->decimal('quantity_kg', 12, 4);
            $table->string('reason', 255);
            $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, APPROVED, DISPATCHED, CANCELLED
            $table->string('debit_memo_number', 50)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_rtvs');
        Schema::dropIfExists('customer_rma_items');
        Schema::dropIfExists('customer_rmas');
    }
};

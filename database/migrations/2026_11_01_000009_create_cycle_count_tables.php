<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_count_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('count_number', 50)->unique();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->nullable()->constrained('warehouse_zones')->nullOnDelete();
            $table->string('count_type', 30)->default('BLIND'); // BLIND, ADHOC, SCHEDULED
            $table->string('status', 30)->default('DRAFT'); // DRAFT, IN_PROGRESS, COUNTED, REVIEW, APPROVED, POSTED, CANCELLED
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index('status');
        });

        Schema::create('cycle_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cycle_count_order_id')->constrained('cycle_count_orders')->cascadeOnDelete();
            $table->foreignUuid('bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->restrictOnDelete();
            $table->decimal('recorded_quantity', 12, 3)->default(0);
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->decimal('variance_quantity', 12, 3)->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, COUNTED, RECOUNT_REQUESTED, APPROVED
            $table->timestamp('counted_at')->nullable();
            $table->text('counter_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['cycle_count_order_id', 'bin_id', 'item_id', 'material_lot_id'],
                'uq_cc_order_bin_item_lot'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_items');
        Schema::dropIfExists('cycle_count_orders');
    }
};

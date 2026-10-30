<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_waves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('wave_number', 50)->unique();
            $table->string('wave_type', 30)->default('DELIVERY_ORDERS'); // DELIVERY_ORDERS, STAGING_DEMANDS
            $table->string('status', 30)->default('DRAFT')->index(); // DRAFT, RELEASED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->string('priority', 20)->default('NORMAL'); // URGENT, NORMAL, LOW
            $table->dateTime('cutoff_time')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('total_orders')->default(0);
            $table->integer('total_lines')->default(0);
            $table->integer('picked_lines')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('picking_wave_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('picking_wave_id')->constrained('picking_waves')->cascadeOnDelete();
            $table->string('orderable_type', 100);
            $table->string('orderable_id', 36);
            $table->string('assigned_tote', 50)->nullable();
            $table->string('status', 30)->default('PENDING')->index(); // PENDING, BATCHED, PICKING, COMPLETED
            $table->timestamps();

            $table->index(['orderable_type', 'orderable_id']);
        });

        Schema::create('picking_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number', 50)->unique();
            $table->foreignUuid('picking_wave_id')->constrained('picking_waves')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cart_code', 50)->nullable();
            $table->string('status', 30)->default('ASSIGNED')->index(); // ASSIGNED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->integer('total_picks')->default(0);
            $table->integer('completed_picks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('picking_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('picking_batch_id')->constrained('picking_batches')->cascadeOnDelete();
            $table->foreignUuid('picking_wave_order_id')->constrained('picking_wave_orders')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('material_lot_id')->constrained('material_lots');
            $table->foreignUuid('warehouse_bin_id')->constrained('warehouse_bins');
            $table->decimal('quantity_to_pick', 12, 4);
            $table->decimal('quantity_picked', 12, 4)->default(0.0000);
            $table->string('target_tote', 50);
            $table->integer('pick_sequence')->default(1)->index();
            $table->string('status', 30)->default('PENDING')->index(); // PENDING, PICKED, SHORT
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_batch_items');
        Schema::dropIfExists('picking_batches');
        Schema::dropIfExists('picking_wave_orders');
        Schema::dropIfExists('picking_waves');
    }
};

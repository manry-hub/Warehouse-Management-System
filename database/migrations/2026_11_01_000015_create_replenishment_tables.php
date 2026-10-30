<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->boolean('is_pick_face')->default(false)->index();
            $table->decimal('min_stock_kg', 12, 3)->nullable();
            $table->decimal('max_stock_kg', 12, 3)->nullable();
        });

        Schema::create('replenishment_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_number', 50)->unique();
            $table->foreignUuid('target_bin_id')->constrained('warehouse_bins');
            $table->foreignUuid('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->decimal('requested_quantity_kg', 12, 4);
            $table->decimal('replenished_quantity_kg', 12, 4)->default(0.0000);
            $table->string('status', 30)->default('PENDING')->index(); // PENDING, ASSIGNED, IN_TRANSIT, COMPLETED, CANCELLED
            $table->string('priority', 20)->default('NORMAL')->index(); // CRITICAL, URGENT, NORMAL
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_tasks');

        Schema::table('warehouse_bins', function (Blueprint $table) {
            $table->dropColumn(['is_pick_face', 'min_stock_kg', 'max_stock_kg']);
        });
    }
};

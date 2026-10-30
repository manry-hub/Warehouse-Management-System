<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 50)->unique();
            $table->string('mes_production_order_id', 100)->index();
            $table->string('target_machine_code', 50)->index();
            $table->string('status', 30)->default('PENDING')->index(); // PENDING, ALLOCATED, PICKED, DISPATCHED_TO_MES, COMPLETED
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['mes_production_order_id', 'status'], 'idx_stg_mes_status');
        });

        Schema::create('staging_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staging_request_id')->constrained('staging_requests')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUuid('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->decimal('quantity_requested', 12, 3);
            $table->decimal('quantity_picked', 12, 3)->default(0);
            $table->foreignUuid('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('staging_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->string('status', 30)->default('PENDING'); // PENDING, ALLOCATED, PICKED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_request_items');
        Schema::dropIfExists('staging_requests');
    }
};

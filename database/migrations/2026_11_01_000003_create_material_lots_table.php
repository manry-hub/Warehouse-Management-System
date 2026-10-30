<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lot_number', 100)->unique();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('vendor_code', 50)->nullable()->index();
            $table->string('vendor_lot_number', 100)->nullable()->index();
            $table->string('vendor_heat_number', 100)->nullable()->index();
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('coa_file_path', 255)->nullable();
            $table->json('coa_metrics')->nullable(); // moisture_pct, melt_flow_index, density
            $table->string('status', 30)->default('QA_HOLD')->index(); // QA_HOLD, AVAILABLE, EXPIRED, QUARANTINE
            $table->timestamp('qc_inspected_at')->nullable();
            $table->foreignId('qc_inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'status', 'expiry_date'], 'idx_lots_item_status_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};

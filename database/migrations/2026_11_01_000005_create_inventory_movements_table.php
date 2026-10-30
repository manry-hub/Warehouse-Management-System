<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->index();
            $table->string('movement_type', 50)->index(); // RECEIPT, PUTAWAY, STAGING_ISSUE, FG_INTAKE, SHIPMENT, ADJUSTMENT
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('material_lot_id')->constrained('material_lots');
            $table->foreignUuid('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignUuid('destination_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('uom', 20)->default('KG');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['reference_id', 'created_at'], 'idx_mov_ref_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUuid('material_lot_id')->constrained('material_lots')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('quantity_reserved', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['bin_id', 'item_id', 'material_lot_id'], 'idx_bal_bin_item_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};

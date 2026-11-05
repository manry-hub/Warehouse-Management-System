<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable();
        });

        // Preserve the historic receipt order for lots that predate FIFO.
        DB::table('material_lots')
            ->whereNull('received_at')
            ->update(['received_at' => DB::raw('created_at')]);

        // Oracle's OCI8 grammar preserves nullable columns on change(), so enforce
        // the post-backfill constraint with Oracle DDL explicitly.
        DB::statement(
            'ALTER TABLE material_lots MODIFY (received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL)'
        );

        Schema::table('material_lots', function (Blueprint $table): void {
            $table->index(['item_id', 'status', 'received_at'], 'idx_lots_item_status_recv');
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table): void {
            $table->dropIndex('idx_lots_item_status_recv');
            $table->dropColumn('received_at');
        });
    }
};

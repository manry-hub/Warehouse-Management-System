<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('allow_regrind')->default(false);
            $table->decimal('max_regrind_percentage', 5, 2)->default(15.00);
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->string('lot_type', 30)->default('VIRGIN')->index();
            $table->foreignUuid('parent_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->string('parent_production_order', 100)->nullable()->index();
            $table->unsignedTinyInteger('regrind_generation')->default(1);
            $table->string('regrind_source_machine', 50)->nullable();
        });

        Schema::table('staging_request_items', function (Blueprint $table) {
            $table->boolean('is_regrind')->default(false);
            $table->decimal('blend_percentage', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staging_request_items', function (Blueprint $table) {
            $table->dropColumn(['is_regrind', 'blend_percentage']);
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_lot_id');
            $table->dropColumn(['lot_type', 'parent_production_order', 'regrind_generation', 'regrind_source_machine']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['allow_regrind', 'max_regrind_percentage']);
        });
    }
};

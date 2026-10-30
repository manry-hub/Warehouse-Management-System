<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('allow_regrind')->default(false)->after('category');
            $table->decimal('max_regrind_percentage', 5, 2)->default(15.00)->after('allow_regrind');
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->string('lot_type', 30)->default('VIRGIN')->after('lot_number')->index();
            $table->foreignUuid('parent_lot_id')->nullable()->after('lot_type')->constrained('material_lots')->nullOnDelete();
            $table->string('parent_production_order', 100)->nullable()->after('parent_lot_id')->index();
            $table->unsignedTinyInteger('regrind_generation')->default(1)->after('parent_production_order');
            $table->string('regrind_source_machine', 50)->nullable()->after('regrind_generation');
        });

        Schema::table('staging_request_items', function (Blueprint $table) {
            $table->boolean('is_regrind')->default(false)->after('material_lot_id');
            $table->decimal('blend_percentage', 5, 2)->nullable()->after('is_regrind');
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

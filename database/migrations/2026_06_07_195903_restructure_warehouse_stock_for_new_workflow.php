<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            // Add new fields for the new workflow
            $table->string('purchase_unit')->nullable()->after('quantity')->comment('Unit used for purchase (Crate, Carton, Box, etc.)');
            $table->integer('quantity_purchased')->nullable()->after('purchase_unit')->comment('Quantity purchased at purchase unit level');
            $table->decimal('total_purchase_cost', 10, 2)->nullable()->after('quantity_purchased')->comment('Total cost for the purchase');
            $table->decimal('calculated_base_unit_cost', 10, 2)->nullable()->after('total_purchase_cost')->comment('Auto-calculated cost per base unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'quantity_purchased', 'total_purchase_cost', 'calculated_base_unit_cost']);
        });
    }
};

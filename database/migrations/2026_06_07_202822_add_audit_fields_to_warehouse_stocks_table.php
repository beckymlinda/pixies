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
            $table->integer('lifetime_quantity_purchased')->default(0)->after('calculated_base_unit_cost')->comment('Total quantity purchased over lifetime');
            $table->integer('lifetime_quantity_sold')->default(0)->after('lifetime_quantity_purchased')->comment('Total quantity sold over lifetime');
            $table->decimal('lifetime_profit_estimate', 10, 2)->default(0)->after('lifetime_quantity_sold')->comment('Estimated lifetime profit');
            $table->decimal('average_unit_cost', 10, 2)->default(0)->after('lifetime_profit_estimate')->comment('Weighted average cost per unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropColumn(['lifetime_quantity_purchased', 'lifetime_quantity_sold', 'lifetime_profit_estimate', 'average_unit_cost']);
        });
    }
};

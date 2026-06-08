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
        Schema::table('items', function (Blueprint $table) {
            $table->string('description')->nullable()->after('category');
            $table->date('expiry_date')->nullable()->after('description');
            $table->decimal('average_unit_cost', 15, 2)->default(0)->after('price');
            $table->integer('lifetime_quantity_purchased')->default(0)->after('director_stock');
            $table->integer('lifetime_quantity_sold')->default(0)->after('lifetime_quantity_purchased');
            $table->decimal('lifetime_profit_estimate', 15, 2)->default(0)->after('lifetime_quantity_sold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['description', 'expiry_date', 'average_unit_cost', 'lifetime_quantity_purchased', 'lifetime_quantity_sold', 'lifetime_profit_estimate']);
        });
    }
};

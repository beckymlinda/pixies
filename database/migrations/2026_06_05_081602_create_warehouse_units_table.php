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
        Schema::create('warehouse_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->onDelete('cascade');
            $table->string('unit_name');
            $table->integer('conversion_factor')->default(1);
            $table->boolean('is_base_unit')->default(false);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->timestamps();
            
            $table->index(['warehouse_stock_id', 'unit_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_units');
    }
};

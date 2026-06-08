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
        Schema::create('warehouse_unit_bar_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_unit_id')->constrained('warehouse_units')->onDelete('cascade');
            $table->unsignedBigInteger('bar_id');
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['warehouse_unit_id', 'bar_id']);
            $table->index(['warehouse_unit_id', 'bar_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_unit_bar_prices');
    }
};

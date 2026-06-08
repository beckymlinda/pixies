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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('unit_name'); // e.g., 'bottle', 'pack', 'case', 'shot', 'casket'
            $table->integer('conversion_factor')->default(1); // e.g., 6 bottles per pack
            $table->boolean('is_base_unit')->default(false); // true for smallest unit
            $table->timestamps();
            
            $table->unique(['item_id', 'unit_name']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};

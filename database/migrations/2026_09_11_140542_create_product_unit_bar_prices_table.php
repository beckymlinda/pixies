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
        Schema::create('product_unit_bar_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bar_id');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->string('unit_name');
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['bar_id', 'item_id', 'unit_name']);
            $table->index(['bar_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_unit_bar_prices');
    }
};

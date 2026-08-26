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
        Schema::create('stock_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->integer('opening_stock')->default(0);
            $table->integer('ordered_stock')->default(0);
            $table->integer('total_stock')->default(0); // opening_stock + ordered_stock
            $table->integer('closing_stock')->default(0);
            $table->integer('sold_quantity')->default(0); // total_stock - closing_stock
            $table->decimal('sales_amount', 10, 2)->default(0); // sold_quantity * item price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_entry_items');
    }
};


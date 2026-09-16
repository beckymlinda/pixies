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
        // Only ever written by the legacy item-management endpoints (removed -
        // they had no UI caller anywhere) and never read back out by anything.
        Schema::dropIfExists('product_purchase_history');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('product_purchase_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('purchase_unit')->comment('The unit purchased (e.g., Crate, Carton)');
            $table->integer('quantity_purchased')->comment('Number of purchase units bought');
            $table->decimal('total_purchase_cost', 15, 2)->comment('Total amount paid');
            $table->decimal('calculated_base_unit_cost', 15, 2)->comment('Cost per base unit');
            $table->string('supplier')->nullable()->comment('Supplier name');
            $table->string('reference_number')->nullable()->comment('Invoice or reference number');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->date('purchase_date')->comment('Date of purchase');
            $table->timestamps();

            $table->index('item_id');
            $table->index('purchase_date');
        });
    }
};

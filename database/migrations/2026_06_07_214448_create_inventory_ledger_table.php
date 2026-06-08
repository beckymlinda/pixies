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
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('bar_id')->nullable();
            $table->unsignedBigInteger('warehouse_stock_id')->nullable();
            $table->string('action_type')->comment('purchase, sale, transfer_in, transfer_out, adjustment');
            $table->integer('quantity')->comment('Positive for additions, negative for deductions');
            $table->decimal('unit_cost', 15, 2)->nullable()->comment('Cost per unit at time of transaction');
            $table->decimal('total_cost', 15, 2)->nullable()->comment('Total cost of transaction');
            $table->integer('balance_after')->comment('Stock quantity after this transaction');
            $table->string('reference_type')->nullable()->comment('purchase_history, warehouse_transfer, sale, etc.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID of related record');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamp('transaction_date')->comment('When the transaction occurred');
            $table->timestamps();
            
            $table->index('item_id');
            $table->index('bar_id');
            $table->index('warehouse_stock_id');
            $table->index('transaction_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};

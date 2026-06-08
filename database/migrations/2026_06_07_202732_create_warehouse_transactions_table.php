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
        Schema::create('warehouse_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->onDelete('cascade');
            $table->enum('transaction_type', ['purchase', 'sale', 'transfer', 'adjustment', 'restock'])->default('purchase');
            $table->integer('quantity')->comment('Positive for additions, negative for deductions');
            $table->decimal('unit_cost', 10, 2)->nullable()->comment('Cost per unit at time of transaction');
            $table->decimal('total_cost', 10, 2)->nullable()->comment('Total cost for this transaction');
            $table->string('supplier')->nullable()->comment('Supplier name for purchases');
            $table->string('reference_number')->nullable()->comment('Invoice or reference number');
            $table->text('notes')->nullable();
            $table->dateTime('transaction_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();
            
            $table->index(['warehouse_stock_id', 'transaction_date']);
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transactions');
    }
};

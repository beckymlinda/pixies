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
        Schema::table('expenses', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['stock_entry_id']);
            
            // Then make the column nullable
            $table->foreignId('stock_entry_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable
            $table->foreign('stock_entry_id')->references('id')->on('daily_stock_entries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Drop the nullable foreign key
            $table->dropForeign(['stock_entry_id']);
            
            // Make it required again
            $table->foreignId('stock_entry_id')->nullable(false)->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('stock_entry_id')->references('id')->on('daily_stock_entries')->onDelete('cascade');
        });
    }
};

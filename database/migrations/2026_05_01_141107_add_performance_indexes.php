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
        // Daily Stock Entries indexes
        Schema::table('daily_stock_entries', function (Blueprint $table) {
            $table->index(['bar_id', 'date'], 'stock_entries_bar_date_index');
            $table->index('date', 'stock_entries_date_index');
            $table->index('user_id', 'stock_entries_user_index');
        });

        // Stock Entry Items indexes
        Schema::table('stock_entry_items', function (Blueprint $table) {
            $table->index('item_id', 'stock_items_item_index');
            $table->index('stock_entry_id', 'stock_items_entry_index');
            $table->index('sold_quantity', 'stock_items_sold_index');
        });

        // Payments indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index('type', 'payments_type_index');
            $table->index('stock_entry_id', 'payments_entry_index');
        });

        // Expenses indexes
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('type', 'expenses_type_index');
            $table->index('stock_entry_id', 'expenses_entry_index');
        });

        // Debts indexes
        Schema::table('debts', function (Blueprint $table) {
            $table->index('seller_id', 'debts_seller_index');
            $table->index('stock_entry_id', 'debts_entry_index');
        });

        // Cash Reconciliations indexes
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->index('status', 'reconciliations_status_index');
            $table->index('verified_by', 'reconciliations_verifier_index');
            $table->index('verified_at', 'reconciliations_verified_index');
        });

        // Bar Item Prices indexes
        Schema::table('bar_item_prices', function (Blueprint $table) {
            $table->index(['bar_id', 'item_id'], 'bar_prices_bar_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes (reverse order)
        Schema::table('bar_item_prices', function (Blueprint $table) {
            $table->dropIndex('bar_prices_bar_item_index');
        });

        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->dropIndex('reconciliations_status_index');
            $table->dropIndex('reconciliations_verifier_index');
            $table->dropIndex('reconciliations_verified_index');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex('debts_seller_index');
            $table->dropIndex('debts_entry_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_type_index');
            $table->dropIndex('expenses_entry_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_type_index');
            $table->dropIndex('payments_entry_index');
        });

        Schema::table('stock_entry_items', function (Blueprint $table) {
            $table->dropIndex('stock_items_item_index');
            $table->dropIndex('stock_items_entry_index');
            $table->dropIndex('stock_items_sold_index');
        });

        Schema::table('daily_stock_entries', function (Blueprint $table) {
            $table->dropIndex('stock_entries_bar_date_index');
            $table->dropIndex('stock_entries_date_index');
            $table->dropIndex('stock_entries_user_index');
        });
    }
};

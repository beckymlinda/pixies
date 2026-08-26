<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_transactions', function (Blueprint $table) {
            $table->foreignId('destination_bar_id')
                ->nullable()
                ->after('warehouse_stock_id')
                ->constrained('bars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_bar_id');
        });
    }
};

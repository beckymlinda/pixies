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
        Schema::create('warehouse_transfer_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_transfer_request_id')
                ->constrained('warehouse_transfer_requests', 'id', 'wtr_req_id_fk')
                ->onDelete('cascade');
            $table->foreignId('warehouse_stock_id')
                ->constrained('warehouse_stocks', 'id', 'wtr_stock_id_fk')
                ->onDelete('cascade');
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items', 'id', 'wtr_item_id_fk')
                ->onDelete('set null');
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->default(0);
            $table->string('unit_name')->nullable();
            $table->integer('conversion_factor')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_request_items');
    }
};

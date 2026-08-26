<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bottle_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_entry_id')->nullable();
            $table->unsignedBigInteger('bar_id');
            $table->unsignedBigInteger('item_id');
            $table->integer('counted')->default(0);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();

            $table->foreign('stock_entry_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('bar_id')->references('id')->on('bars')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->index(['bar_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bottle_counts');
    }
};


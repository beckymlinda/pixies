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
        Schema::create('order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bar_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, approved, partially_approved, denied
            $table->text('notes')->nullable();
            $table->boolean('seller_notified')->default(false);
            $table->date('date');
            $table->timestamps();
        });

        Schema::create('order_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->integer('requested_quantity');
            $table->integer('approved_quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_request_items');
        Schema::dropIfExists('order_requests');
    }
};

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
        Schema::create('warehouse_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bar_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status')->default('pending')->comment('pending, approved, partially_approved, rejected');
            $table->text('notes')->nullable()->comment('Request notes');
            $table->text('rejection_reason')->nullable()->comment('Reason for rejection');
            $table->timestamp('requested_at')->comment('When request was made');
            $table->timestamp('approved_at')->nullable()->comment('When request was approved/rejected');
            $table->timestamps();
            
            $table->index('bar_id');
            $table->index('requested_by');
            $table->index('status');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_requests');
    }
};

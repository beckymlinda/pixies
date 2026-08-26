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
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('sales')->onDelete('cascade');
            $table->decimal('expected_cash', 10, 2);
            $table->decimal('cash_counted', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->enum('status', ['matched', 'shortage', 'excess'])->default('matched');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->unique('stock_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliations');
    }
};


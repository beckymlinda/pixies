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
        Schema::table('daily_stock_entries', function (Blueprint $table) {
            $table->enum('status', ['pending', 'verified', 'flagged'])->default('pending')->after('date');
            $table->text('notes')->nullable()->after('status');
            $table->timestamp('verified_at')->nullable()->after('notes');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null')->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_stock_entries', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['status', 'notes', 'verified_at', 'verified_by']);
        });
    }
};

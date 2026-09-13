<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Descriptions include a unit-price summary and (now) a per-bar rename
        // note, easily exceeding the original VARCHAR(255). MySQL enforces
        // that length strictly (truncation errors instead of clipping), so
        // it needs widening; SQLite (used in tests) has no such limit to begin
        // with and doesn't support this ALTER syntax, so skip there.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE activity_logs MODIFY description TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE activity_logs MODIFY description VARCHAR(255) NULL');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bar_id')->nullable()->after('user_id')->constrained('bars')->nullOnDelete();
            $table->boolean('is_overhead')->default(false)->after('bar_id');
        });

        // Existing standalone expenses were manager/director entries — treat as overhead.
        if (Schema::hasColumn('expenses', 'stock_entry_id')) {
            DB::table('expenses')
                ->whereNull('stock_entry_id')
                ->update(['is_overhead' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bar_id');
            $table->dropColumn('is_overhead');
        });
    }
};

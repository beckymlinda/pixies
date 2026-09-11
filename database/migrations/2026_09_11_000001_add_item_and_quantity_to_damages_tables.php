<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('type')->constrained('items')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->nullable()->after('item_id');
        });

        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('bar_id')->constrained('items')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('quantity');
        });

        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('quantity');
        });
    }
};

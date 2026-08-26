<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('amount');
            $table->boolean('from_balance')->default(false)->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'from_balance']);
        });
    }
};

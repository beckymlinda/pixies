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
        Schema::create('item_bar_names', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bar_id');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();

            $table->unique(['bar_id', 'item_id']);
            $table->index(['bar_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_bar_names');
    }
};

<?php

use App\Models\Bar;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $bar = Bar::where('name', 'Pixies Njerwa')->first();

        if (! $bar) {
            return;
        }

        User::where('bar_id', $bar->id)->update(['bar_id' => null]);

        $fallbackBar = Bar::listed()->orderBy('name')->first();
        if ($fallbackBar) {
            User::whereNull('bar_id')
                ->where('role', 'seller')
                ->update(['bar_id' => $fallbackBar->id]);
        }

        $bar->delete();
    }

    public function down(): void
    {
        Bar::firstOrCreate(['name' => 'Pixies Njerwa']);
    }
};

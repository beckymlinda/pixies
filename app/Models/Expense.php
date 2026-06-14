<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const SHIFT_DEBT_PREFIX = '[shift]';

    public static function expenditureTypes(): array
    {
        return [
            'lunch' => 'Lunch',
            'water' => 'Water',
            'escom' => 'ESCOM',
            'taxi' => 'Taxi',
            'debt' => 'Debt (Credit Sale)',
            'damages' => 'Damages',
            'maintenance' => 'Maintenance',
        ];
    }

    public static function operationalTypes(): array
    {
        return collect(self::expenditureTypes())
            ->except('debt')
            ->all();
    }

    public static function typeLabel(string $type): string
    {
        return self::expenditureTypes()[$type] ?? ucfirst($type);
    }

    protected $fillable = ['stock_entry_id', 'type', 'amount', 'description', 'date', 'user_id'];

    public function stockEntry()
    {
        return $this->belongsTo(DailyStockEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

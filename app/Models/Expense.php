<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const SHIFT_DEBT_PREFIX = '[shift]';
    public const SHIFT_DAMAGE_PREFIX = '[damage]';

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

    /** Expenditure types shown on the Balance (shift reporting) form. */
    public static function balanceExpenditureTypes(): array
    {
        return [
            'lunch' => 'Lunch',
            'taxi' => 'Transport',
            'damages' => 'Damages',
            'debt' => 'Ngongole',
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

    protected $fillable = ['stock_entry_id', 'type', 'item_id', 'quantity', 'amount', 'description', 'date', 'user_id', 'bar_id', 'is_overhead'];

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function stockEntry()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBarOperating($query)
    {
        return $query->where('is_overhead', false);
    }

    public function scopeOverhead($query)
    {
        return $query->where('is_overhead', true);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForBarContext($query, ?int $barId)
    {
        if (!$barId) {
            return $query;
        }

        return $query->where(function ($q) use ($barId) {
            $q->where('bar_id', $barId)
                ->orWhereHas('stockEntry', fn ($sq) => $sq->where('bar_id', $barId))
                ->orWhereHas('user', fn ($uq) => $uq->where('bar_id', $barId));
        });
    }

    public static function barOperatingBetween($startDate, $endDate, ?int $barId = null)
    {
        return static::query()
            ->barOperating()
            ->where(function ($outer) use ($startDate, $endDate, $barId) {
                $outer->whereHas('stockEntry', function ($sq) use ($startDate, $endDate, $barId) {
                    $sq->whereBetween('date', [$startDate, $endDate]);
                    if ($barId) {
                        $sq->where('bar_id', $barId);
                    }
                })->orWhere(function ($uq) use ($startDate, $endDate, $barId) {
                    $uq->whereNull('stock_entry_id')->whereBetween('date', [$startDate, $endDate]);
                    if ($barId) {
                        $uq->forBarContext($barId);
                    }
                });
            });
    }

    public static function overheadBetween($startDate, $endDate, ?int $barId = null)
    {
        return static::query()
            ->overhead()
            ->whereBetween('date', [$startDate, $endDate])
            ->when($barId, fn ($q) => $q->where('bar_id', $barId));
    }
}


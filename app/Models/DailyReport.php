<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bar_id',
        'date',
        'cash_in_hand',
        'total_sales',
        'total_payments',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'cash_in_hand' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_payments' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DailyReportPayment::class);
    }

    /**
     * Get total payments by method
     */
    public function getPaymentsByMethod(): array
    {
        return $this->payments()
            ->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->pluck('total', 'payment_method')
            ->toArray();
    }

    /**
     * Calculate cash balance (cash in hand - total payments)
     */
    public function getCashBalanceAttribute(): float
    {
        return $this->cash_in_hand - $this->total_payments;
    }

    /**
     * Get today's report for the current user
     */
    public static function getTodayReport(): ?self
    {
        $user = auth()->user();
        
        if (!$user || !$user->bar_id) {
            return null;
        }

        return self::where('user_id', $user->id)
            ->where('bar_id', $user->bar_id)
            ->where('date', now()->format('Y-m-d'))
            ->first();
    }
}

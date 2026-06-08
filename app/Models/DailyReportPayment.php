<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_report_id',
        'payment_method',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Get available payment methods
     */
    public static function getPaymentMethods(): array
    {
        return [
            'Airtel Money' => 'Airtel Money',
            'Mpamba' => 'Mpamba',
            'POS' => 'POS',
            'Cash' => 'Cash',
        ];
    }
}

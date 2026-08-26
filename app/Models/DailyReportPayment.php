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
            'MO626' => 'MO626',
            'POS' => 'POS',
            'Cash' => 'Cash',
        ];
    }

    public static function paymentMethodKeys(): string
    {
        return implode(',', array_keys(self::getPaymentMethods()));
    }

    public static function isCashMethod(string $method): bool
    {
        return $method === 'Cash';
    }

    /** Parse Pay Through amount field (comma-separated for non-cash methods). */
    public static function parseAmountInput(string $method, string $raw): array
    {
        $raw = trim($raw);

        if (!self::isCashMethod($method) && str_contains($raw, ',')) {
            $parts = collect(explode(',', $raw))
                ->map(fn ($part) => trim($part))
                ->filter(fn ($part) => $part !== '');

            $total = $parts->sum(fn ($part) => (float) preg_replace('/[^\d.]/', '', $part));
            $breakdown = $parts->implode(', ');

            return [
                'amount' => $total,
                'description' => $breakdown,
            ];
        }

        return [
            'amount' => (float) preg_replace('/[^\d.]/', '', $raw),
            'description' => null,
        ];
    }

    public static function amountDisplayForForm(string $method, $amount, ?string $description): string
    {
        if (!self::isCashMethod($method) && filled($description)) {
            return $description;
        }

        return (string) $amount;
    }
}

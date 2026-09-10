<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A plain-language title for the action, for non-technical readers.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'stock_updated' => 'Stock Updated',
            'stock_added' => 'Stock Added',
            'item_created' => 'New Item Created',
            'item_updated' => 'Item Updated',
            'item_deleted' => 'Item Removed',
            'daily_report_submitted' => 'Balance Submitted',
            'daily_report_resubmitted' => 'Balance Resubmitted',
            'daily_report_updated' => 'Balance Updated',
            'daily_report_deleted' => 'Balance Deleted',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * A Bootstrap icon that matches the action.
     */
    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'stock_updated' => 'bi-box-seam',
            'stock_added' => 'bi-plus-circle',
            'item_created' => 'bi-plus-square',
            'item_updated' => 'bi-pencil-square',
            'item_deleted' => 'bi-trash',
            'daily_report_submitted', 'daily_report_resubmitted', 'daily_report_updated' => 'bi-clipboard-check',
            'daily_report_deleted' => 'bi-clipboard-x',
            default => 'bi-activity',
        };
    }

    /**
     * A Bootstrap color class that matches the action.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'stock_updated' => 'warning',
            'stock_added' => 'success',
            'item_created' => 'info',
            'item_updated' => 'primary',
            'item_deleted' => 'danger',
            'daily_report_submitted', 'daily_report_resubmitted' => 'success',
            'daily_report_updated' => 'primary',
            'daily_report_deleted' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Human-readable before/after changes, skipping unchanged and nested fields.
     */
    public function getFriendlyChangesAttribute(): array
    {
        $labels = [
            'stock' => 'Stock quantity',
            'price' => 'Selling price',
            'base_price' => 'Base price',
            'bar' => 'Bar',
            'category' => 'Category',
        ];

        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changes = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            if ($key === 'units' || is_array($old[$key] ?? null) || is_array($new[$key] ?? null)) {
                continue; // nested data is summarised separately
            }

            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal === $newVal) {
                continue;
            }

            $changes[] = [
                'label' => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'old' => $this->formatValue($oldVal),
                'new' => $this->formatValue($newVal),
            ];
        }

        return $changes;
    }

    /**
     * A plain-language summary of the selling units, e.g. "Bottle at MWK 1,000".
     */
    public function getUnitsSummaryAttribute(): ?string
    {
        $units = $this->new_values['units'] ?? null;
        if (!is_array($units) || empty($units)) {
            return null;
        }

        return collect($units)
            ->map(function ($unit) {
                $name = $unit['unit_name'] ?? 'Unit';
                $price = number_format((float) ($unit['selling_price'] ?? 0));

                return "{$name} at MWK {$price}";
            })
            ->implode(' • ');
    }

    /**
     * Format a scalar value for display (money-like numbers get thousands separators).
     */
    protected function formatValue($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2);
        }

        return (string) $value;
    }

    /**
     * Log an activity
     */
    public static function log(array $data): self
    {
        return self::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'action' => $data['action'],
            'description' => $data['description'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'ip_address' => request()->ip(),
        ]);
    }
}

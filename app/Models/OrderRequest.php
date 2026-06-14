<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRequest extends Model
{
    protected $fillable = [
        'user_id',
        'bar_id',
        'status', // pending, approved, partially_approved, denied
        'notes',
        'seller_notified',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'seller_notified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function items()
    {
        return $this->hasMany(OrderRequestItem::class);
    }

    public static function unseenPendingCountForDirector(): int
    {
        $seenIds = session('director_seen_order_ids', []);

        return self::where('status', 'pending')
            ->when(! empty($seenIds), fn ($q) => $q->whereNotIn('id', $seenIds))
            ->count();
    }

    public static function markDirectorPendingAsSeen(array $extraIds = []): void
    {
        $pendingIds = self::where('status', 'pending')->pluck('id')->all();
        $seenIds = session('director_seen_order_ids', []);
        session([
            'director_seen_order_ids' => array_values(array_unique(array_merge($seenIds, $pendingIds, $extraIds))),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStockEntry extends Model
{
    protected $fillable = [
        'bar_id', 
        'user_id', 
        'date', 
        'status', 
        'notes', 
        'verified_at', 
        'verified_by'
    ];

    protected $casts = [
        'date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stockEntryItems()
    {
        return $this->hasMany(StockEntryItem::class, 'stock_entry_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'stock_entry_id');
    }

    public function debts()
    {
        return $this->hasMany(Debt::class, 'stock_entry_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'stock_entry_id');
    }

    public function cashReconciliation()
    {
        return $this->hasOne(CashReconciliation::class, 'stock_entry_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified()
    {
        return $this->status === 'verified' && $this->verified_at !== null;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isFlagged()
    {
        return $this->status === 'flagged';
    }

    public function markAsVerified($verifiedBy, $notes = null)
    {
        $this->status = 'verified';
        $this->verified_at = now();
        $this->verified_by = $verifiedBy;
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    public function markAsFlagged($notes = null)
    {
        $this->status = 'flagged';
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'verified' => 'green',
            'pending' => 'yellow',
            'flagged' => 'red',
            default => 'gray'
        };
    }

    public function getStatusIcon()
    {
        return match($this->status) {
            'verified' => '✅',
            'pending' => '⏳',
            'flagged' => '🚩',
            default => '❓'
        };
    }
}

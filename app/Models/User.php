<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'bar_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function dailyStockEntries()
    {
        return $this->hasMany(DailyStockEntry::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class, 'seller_id');
    }

    public function isSeller()
    {
        return $this->role === 'seller';
    }

    public function isManager()
    {
        return $this->role === 'manager';
    }

    public function isDirector()
    {
        return $this->role === 'director';
    }

    public function isAdmin()
    {
        return in_array($this->role, ['manager', 'director']);
    }

    public function hasRole($role)
    {
        if (str_contains($role, '|')) {
            return in_array($this->role, explode('|', $role));
        }

        if (str_contains($role, ',')) {
            return in_array($this->role, array_map('trim', explode(',', $role)));
        }

        return $this->role === $role;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PokerTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'unit',
        'status',
        'pin_hash',
        'pin_plain',
        'closed_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($table) {
            if (empty($table->code)) {
                $table->code = strtoupper(Str::random(6));
            }
        });
    }

    public function players(): HasMany
    {
        return $this->hasMany(TablePlayer::class);
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(TableSnapshot::class);
    }

    public function verifyPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin_hash) || ($this->pin_plain !== null && $this->pin_plain === $pin);
    }

    public function totalBuyins(): float
    {
        return (float) $this->players()
            ->with('buyins')
            ->get()
            ->sum(fn ($p) => $p->buyins->sum('amount'));
    }

    public function activePlayersCount(): int
    {
        return $this->players()->where('status', 'active')->count();
    }
}

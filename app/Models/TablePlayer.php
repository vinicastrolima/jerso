<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TablePlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'poker_table_id',
        'player_id',
        'name',
        'status',
        'final_amount',
        'cashed_out_at',
    ];

    protected $casts = [
        'final_amount' => 'float',
        'cashed_out_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(PokerTable::class, 'poker_table_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function buyins(): HasMany
    {
        return $this->hasMany(Buyin::class);
    }

    public function totalBuyins(): float
    {
        return (float) $this->buyins()->sum('amount');
    }

    public function profit(): ?float
    {
        if ($this->final_amount === null && $this->status !== 'cashed_out') {
            return null;
        }

        return (float) ($this->final_amount ?? 0) - $this->totalBuyins();
    }
}

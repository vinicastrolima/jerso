<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Buyin extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_player_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function tablePlayer(): BelongsTo
    {
        return $this->belongsTo(TablePlayer::class);
    }
}

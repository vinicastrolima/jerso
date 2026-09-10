<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'poker_table_id',
        'total_buyins',
        'total_players',
        'ranking_json',
        'settlements_json',
        'summary_json',
    ];

    protected $casts = [
        'total_buyins' => 'float',
        'total_players' => 'integer',
        'ranking_json' => 'array',
        'settlements_json' => 'array',
        'summary_json' => 'array',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(PokerTable::class, 'poker_table_id');
    }
}

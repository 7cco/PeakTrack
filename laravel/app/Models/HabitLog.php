<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitLog extends Model
{
    protected $fillable = [
        'user_id',
        'habit_id',
        'log_date',
        'value',
        'is_record',
        'record_type',
    ];

    protected $casts = [
        'log_date'  => 'date',
        'value'     => 'float',
        'is_record' => 'boolean',
    ];

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

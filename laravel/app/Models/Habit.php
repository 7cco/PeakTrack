<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Habit extends Model
{
    # 1. МАССОВОЕ ЗАПОЛНЕНИЕ (Fillable)
    protected $fillable = [
        'user_id',
        'name',
        'metric_type',
        'target_value',
        'unit',
        'is_active',
        'is_public',
    ];

    # 2. ПРИВЕДЕНИЕ ТИПОВ (Casts)
    protected $casts = [
        'target_value' => 'decimal:2', // В БД decimal(8,2)
        'is_active'    => 'boolean',
        'is_public'    => 'boolean',
    ];

    # 3. СВЯЗИ (Relationships)
    
    // Привычка принадлежит пользователю
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // У привычки может быть много логов выполнения
    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    /**
     * 4. СКОУПЫ
     * Позволяет писать Habit::active()->get() вместо Habit::where('is_active', true)->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
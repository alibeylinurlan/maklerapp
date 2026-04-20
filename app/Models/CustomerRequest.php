<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRequest extends Model
{

    protected $fillable = [
        'customer_id',
        'user_id',
        'name',
        'filters',
        'is_active',
        'in_progress',
        'priority',
        'last_matched_at',
        'notify_telegram',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_active' => 'boolean',
            'in_progress' => 'boolean',
            'priority' => 'integer',
            'last_matched_at' => 'datetime',
            'notify_telegram' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(PropertyMatch::class);
    }
}

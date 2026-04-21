<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'whatsapp',
        'notes',
        'is_active',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    public function touchActivity(): void
    {
        $this->timestamps = false;
        $this->update(['last_activity_at' => now()]);
        $this->timestamps = true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CustomerRequest::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(\App\Models\PropertyMatch::class);
    }
}

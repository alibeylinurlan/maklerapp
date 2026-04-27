<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'whatsapp',
        'notes',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
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

    public function properties(): HasMany
    {
        return $this->hasMany(SellerProperty::class);
    }
}

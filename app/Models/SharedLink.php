<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SharedLink extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'customer_id',
        'property_ids',
        'title',
        'message',
        'expires_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'property_ids' => 'array',
            'expires_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SharedLink $link) {
            if (empty($link->uuid)) {
                $link->uuid = Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function properties()
    {
        return Property::whereIn('id', $this->property_ids ?? [])->get();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}

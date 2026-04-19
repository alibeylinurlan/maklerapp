<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyMatch extends Model
{
    protected $fillable = [
        'property_id',
        'customer_request_id',
        'user_id',
        'customer_id',
        'status',
        'dismissed_at',
        'notified_at',
        'notification_channel',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
            'notified_at'  => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

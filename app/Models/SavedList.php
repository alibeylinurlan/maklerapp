<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SavedList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'is_shared',
        'share_token',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_shared'        => 'boolean',
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

    public function items(): HasMany
    {
        return $this->hasMany(SavedListItem::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'saved_list_items')
            ->withTimestamps();
    }
}

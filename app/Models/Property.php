<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'bina_id',
        'category_id',
        'title',
        'price',
        'currency',
        'area',
        'rooms',
        'floor',
        'floor_total',
        'location_full_name',
        'location_id',
        'path',
        'photos',
        'has_mortgage',
        'has_repair',
        'has_bill_of_sale',
        'is_leased',
        'is_business',
        'is_vipped',
        'is_featured',
        'is_owner',
        'owner_checked_at',
        'bumped_at',
        'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'area' => 'decimal:2',
            'rooms' => 'integer',
            'photos' => 'array',
            'floor_total' => 'integer',
            'has_mortgage' => 'boolean',
            'has_repair' => 'boolean',
            'has_bill_of_sale' => 'boolean',
            'is_leased' => 'boolean',
            'is_business' => 'boolean',
            'is_vipped' => 'boolean',
            'is_featured' => 'boolean',
            'is_owner' => 'boolean',
            'owner_checked_at' => 'datetime',
            'bumped_at' => 'datetime',
            'first_seen_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(PropertyMatch::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderByDesc('recorded_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PropertyNote::class)->orderByDesc('created_at');
    }

    public function getFullUrlAttribute(): string
    {
        return 'https://bina.az' . $this->path;
    }
}

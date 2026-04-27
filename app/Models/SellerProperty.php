<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PriceHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerProperty extends Model
{
    protected $fillable = [
        'seller_id',
        'user_id',
        'title',
        'category_id',
        'location_id',
        'price',
        'currency',
        'rooms',
        'area',
        'floor',
        'floor_total',
        'notes',
        'photos',
        'bina_url',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'price'  => 'float',
            'photos' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function priceHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderBy('recorded_at');
    }

    public function recordPrice(): void
    {
        if (!$this->price) return;
        $last = $this->priceHistory()->latest('recorded_at')->first();
        if (!$last || (float)$last->price !== (float)$this->price) {
            PriceHistory::create([
                'seller_property_id' => $this->id,
                'price'              => $this->price,
                'currency'           => $this->currency ?? 'AZN',
                'recorded_at'        => now(),
            ]);
        }
    }
}

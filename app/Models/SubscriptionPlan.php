<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['key', 'name_az', 'description_az', 'price', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function userPlans(): HasMany
    {
        return $this->hasMany(UserPlan::class, 'plan_id');
    }

    public function activeUserPlans(): HasMany
    {
        return $this->hasMany(UserPlan::class, 'plan_id')
            ->where('is_active', true)
            ->where('expires_at', '>', now());
    }
}

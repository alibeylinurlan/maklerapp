<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'email',
        'phone',
        'company_name',
        'telegram_user_id',
        'subscription_plan',
        'subscription_expires_at',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function childrenIds(): array
    {
        return $this->children()->pluck('id')->toArray();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerRequests(): HasMany
    {
        return $this->hasMany(CustomerRequest::class);
    }

    public function propertyMatches(): HasMany
    {
        return $this->hasMany(PropertyMatch::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function sharedLinks(): HasMany
    {
        return $this->hasMany(SharedLink::class);
    }

    public function userPlans(): HasMany
    {
        return $this->hasMany(UserPlan::class);
    }

    public function activePlans(): HasMany
    {
        return $this->hasMany(UserPlan::class)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('plan');
    }

    public function hasPlan(string $planKey): bool
    {
        return $this->userPlans()
            ->whereHas('plan', fn(Builder $q) => $q->where('key', $planKey))
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function hasFeature(string $featureKey): bool
    {
        return in_array($featureKey, $this->activeFeatureKeys());
    }

    public function activeFeatureKeys(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "user_features:{$this->id}",
            $this->featureCacheTtl(),
            fn() => $this->userPlans()
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->with('plan.features')
                ->get()
                ->flatMap(fn($up) => $up->plan->features->pluck('key'))
                ->unique()
                ->values()
                ->toArray()
        );
    }

    private function featureCacheTtl(): int
    {
        $earliest = $this->userPlans()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->min('expires_at');

        if (!$earliest) return 300;

        $seconds = now()->diffInSeconds(\Carbon\Carbon::parse($earliest));
        return max($seconds, 60);
    }

    public function isPremium(): bool
    {
        return in_array($this->subscription_plan, ['premium', 'enterprise'])
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function isEnterprise(): bool
    {
        return $this->subscription_plan === 'enterprise'
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function canUse(string $feature): bool
    {
        return match ($feature) {
            'telegram_notifications' => $this->isPremium(),
            'unlimited_customers' => $this->isEnterprise(),
            'unlimited_requests' => $this->isEnterprise(),
            'realtime_matching' => $this->isPremium(),
            default => true,
        };
    }
}

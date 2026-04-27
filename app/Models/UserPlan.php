<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class UserPlan extends Model
{
    protected $fillable = ['user_id', 'plan_id', 'starts_at', 'expires_at', 'assigned_by', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function (self $up) {
            Cache::forget("user_features:{$up->user_id}");
            // Yalnız plan aktivləşdiriləndə limit tətbiq et
            if ($up->is_active) {
                static::enforceTelegramNotifyLimit($up->user_id);
            }
        });
        static::deleted(function (self $up) {
            Cache::forget("user_features:{$up->user_id}");
        });
    }

    // Plan dəyişdikdə aktiv telegram bildirişlərini limitleyir
    private static function enforceTelegramNotifyLimit(int $userId): void
    {
        $planLimits = ['ultra' => 10, 'premium' => 1];
        $limit = 0;

        $activePlanKey = static::where('user_id', $userId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('plan')
            ->get()
            ->map(fn($up) => $up->plan?->key)
            ->filter()
            ->first(fn($key) => isset($planLimits[$key]));

        if ($activePlanKey) {
            $limit = $planLimits[$activePlanKey];
        }

        $active = \App\Models\CustomerRequest::where('user_id', $userId)
            ->where('notify_telegram', true)
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($active->count() > $limit) {
            $toDisable = $active->slice($limit)->pluck('id');
            \App\Models\CustomerRequest::whereIn('id', $toDisable)
                ->update(['notify_telegram' => false]);
        }
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }
}

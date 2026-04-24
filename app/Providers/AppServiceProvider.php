<?php

namespace App\Providers;

use App\Models\Property;
use App\Observers\PropertyObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Property::observe(PropertyObserver::class);

        // @hasFeature('key') ... @else ... @endhasFeature
        Blade::if('hasFeature', function (string $key) {
            $user = auth()->user();
            if (!$user) return false;
            if ($user->hasAnyRole(['superadmin', 'admin', 'developer'])) return true;
            return $user->hasFeature($key);
        });

        // @hasAnyFeature(['key1','key2']) veya @hasAnyFeature('key1','key2')
        Blade::if('hasAnyFeature', function (string|array ...$keys) {
            $user = auth()->user();
            if (!$user) return false;
            if ($user->hasAnyRole(['superadmin', 'admin', 'developer'])) return true;
            $flat = collect($keys)->flatten()->toArray();
            foreach ($flat as $key) {
                if ($user->hasFeature($key)) return true;
            }
            return false;
        });

        // Superadmin bütün permission-ları bypass edir
        Gate::before(function ($user) {
            if ($user->hasAnyRole(['superadmin', 'developer'])) {
                return true;
            }
        });
    }
}

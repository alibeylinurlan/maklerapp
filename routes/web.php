<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Guest routes
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    Volt::route('/register', 'auth.register')->name('register');
});

// Auth routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('properties.index'));

    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
    Volt::route('/properties', 'properties.index')->name('properties.index');
    Volt::route('/customers', 'customers.index')->name('customers.index');
    Route::get('/customer-requests', fn() => redirect()->route('customers.index'))->name('customer-requests.index');
    Route::get('/matches', fn() => redirect()->route('customers.index'))->name('matches.index');
    Volt::route('/pricing', 'pricing')->name('pricing');

    // Developer test route
    Route::post('/dev/test-socket', function () {
        if (!auth()->user()->hasRole('developer|superadmin|admin')) abort(403);
        $fake = [
            'id'          => 999999 + rand(1, 999),
            'price'       => number_format(rand(40000, 200000)) . ' ₼',
            'rooms'       => rand(1, 5),
            'area'        => rand(40, 150),
            'floor'       => rand(1, 12),
            'floor_total' => 16,
            'location'    => 'Bakı, Nəsimi r.',
            'category'    => 'Mənzil',
            'thumb'       => null,
            'url'         => '#',
        ];
        \Illuminate\Support\Facades\Redis::publish('properties.new', json_encode($fake));
        return response()->json(['ok' => true]);
    })->name('dev.test-socket');

    // Admin panel
    Route::middleware('role:superadmin|admin')->prefix('admin')->group(function () {
        Volt::route('/users', 'admin.users.index')->name('admin.users');
        Volt::route('/roles', 'admin.roles.index')->name('admin.roles');
        Volt::route('/plans', 'admin.plans.index')->name('admin.plans');
    });

    Route::get('/api/properties/new', function () {
        $since = (int) request('since', 0);
        $props = \App\Models\Property::with('category')
            ->where('is_owner', true)
            ->where('id', '>', $since)
            ->orderByDesc('id')
            ->take(30)
            ->get()
            ->map(function ($p) {
                $thumb = null;
                if (!empty($p->photos)) {
                    $thumb = $p->photos[0]['thumb'] ?? $p->photos[0]['medium'] ?? null;
                }
                return [
                    'id'          => $p->id,
                    'price'       => $p->price ? number_format($p->price) . ' ' . ($p->currency === 'azn' ? '₼' : '$') : null,
                    'rooms'       => $p->rooms,
                    'area'        => $p->area,
                    'floor'       => $p->floor,
                    'floor_total' => $p->floor_total,
                    'location'    => $p->location_full_name,
                    'category'    => $p->category?->name_az,
                    'thumb'       => $thumb,
                    'url'         => $p->full_url,
                    'at'          => $p->bumped_at?->diffForHumans() ?? $p->created_at?->diffForHumans(),
                    'created_at'  => $p->bumped_at?->toISOString() ?? $p->created_at?->toISOString(),
                ];
            });
        return response()->json($props);
    })->name('api.properties.new');

    Route::post('/logout', function () {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('/login');
    })->name('logout');
});

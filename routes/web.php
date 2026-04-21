<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Guest routes
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth.login')->name('login');
    // Qeydiyyat müvəqqəti bağlıdır
    // Volt::route('/register', 'auth.register')->name('register');
    Route::get('/register', fn() => redirect()->route('login'))->name('register');
});

// Public routes
Volt::route('/pricing', 'pricing')->name('pricing');

// Auth routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('properties.index'));

    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
    Volt::route('/properties', 'properties.index')->name('properties.index');
    Volt::route('/properties/saved', 'properties.saved-lists')->name('properties.saved');
    Volt::route('/customers', 'customers.index')->name('customers.index');
    Route::get('/customer-requests', fn() => redirect()->route('customers.index'))->name('customer-requests.index');
    Route::get('/matches', fn() => redirect()->route('customers.index'))->name('matches.index');
    Volt::route('/settings', 'settings.index')->name('settings');

    // Developer test route
    Route::post('/dev/test-socket', function () {
        if (!auth()->user()->hasRole('developer')) abort(403);
        $colors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#0284c7'];
        $bg = $colors[array_rand($colors)];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200"><rect width="300" height="200" fill="' . $bg . '"/><text x="150" y="110" font-size="48" text-anchor="middle" fill="rgba(255,255,255,0.3)">🏠</text></svg>';
        $thumb = 'data:image/svg+xml;base64,' . base64_encode($svg);
        $fake = [
            'id'          => 999999 + rand(1, 999),
            'price'       => number_format(rand(40000, 200000)) . ' ₼',
            'rooms'       => rand(1, 5),
            'area'        => rand(40, 150),
            'floor'       => rand(1, 12),
            'floor_total' => 16,
            'location'    => 'Bakı, Nəsimi r.',
            'category'    => 'Mənzil',
            'thumb'       => $thumb,
            'url'         => '#',
        ];
        \Illuminate\Support\Facades\Redis::publish('makler-database-properties.new', json_encode($fake));
        return response()->json(['ok' => true]);
    })->name('dev.test-socket');

    // Developer panel
    Route::middleware('role:developer')->prefix('dev')->group(function () {
        Volt::route('/logs', 'dev.logs')->name('dev.logs');
    });

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

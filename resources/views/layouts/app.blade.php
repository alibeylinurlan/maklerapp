<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Makler' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    @auth
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 lg:w-64">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-2 py-3">
            <flux:icon.home-modern class="size-6 text-indigo-600" />
            <span class="text-lg font-bold text-zinc-800 dark:text-white">Makler</span>
        </a>

        @php
            $isAdminOrSuper = auth()->user()->hasAnyRole(['superadmin', 'admin']);
            $hasPlatform = $isAdminOrSuper || auth()->user()->hasPlan('platform');
            $hasRequests = $isAdminOrSuper || auth()->user()->hasPlan('requests');
            $hasMatches  = $hasRequests;
        @endphp

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Əsas">
                <flux:navlist.item icon="chart-bar-square" :href="route('dashboard')" :current="request()->routeIs('dashboard')">
                    Dashboard
                </flux:navlist.item>
                <flux:navlist.item
                    icon="building-office"
                    :icon:trailing="$hasPlatform ? null : 'lock-closed'"
                    :href="route('properties.index')"
                    :current="request()->routeIs('properties.*')"
                >Elanlar</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Müştərilər">
                <flux:navlist.item
                    icon="users"
                    :icon:trailing="$hasRequests ? null : 'lock-closed'"
                    :href="route('customers.index')"
                    :current="request()->routeIs('customers.*')"
                >Müştərilər</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        @if(auth()->user()->hasAnyRole(['superadmin', 'admin']))
        <flux:navlist variant="outline">
            <flux:navlist.group heading="Admin">
                <flux:navlist.item icon="user-group" :href="route('admin.users')" :current="request()->routeIs('admin.users')">
                    İstifadəçilər
                </flux:navlist.item>
                <flux:navlist.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')">
                    Rollar
                </flux:navlist.item>
                <flux:navlist.item icon="credit-card" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')">
                    Abunəliklər
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
        @endif

        @if(!$isAdminOrSuper)
        <flux:navlist variant="outline">
            <flux:navlist.item icon="credit-card" :href="route('pricing')" :current="request()->routeIs('pricing')">
                Paketlər
            </flux:navlist.item>
        </flux:navlist>
        @endif

        <flux:spacer />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:navlist variant="outline">
                <flux:navlist.item icon="arrow-right-start-on-rectangle" href="#" x-on:click.prevent="$el.closest('form').submit()">
                    Çıxış
                </flux:navlist.item>
            </flux:navlist>
        </form>

        <div class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ auth()->user()->name }}</div>
            <div class="text-xs text-zinc-500">{{ ucfirst(auth()->user()->subscription_plan) }} plan</div>
        </div>
    </flux:sidebar>

    <flux:main>
        <div class="flex items-center gap-3 mb-6 lg:hidden">
            <flux:sidebar.toggle icon="bars-3" class="lg:hidden" />
            <span class="text-lg font-bold">Makler</span>
        </div>

        {{ $slot }}
    </flux:main>
    @endauth

    @guest
        {{ $slot }}
    @endguest

    @fluxScripts
    <script src="/socket.io.min.js"></script>
</body>
</html>

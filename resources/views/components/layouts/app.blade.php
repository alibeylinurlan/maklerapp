<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Binokl.az' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    @auth
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 lg:w-64">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-2 py-3">
            <flux:icon.home-modern class="size-6 text-indigo-600" />
            <span class="text-lg font-bold text-zinc-800 dark:text-white">Binokl.az</span>
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Əsas">
                <flux:navlist.item icon="chart-bar-square" :href="route('dashboard')" :current="request()->routeIs('dashboard')">
                    Dashboard
                </flux:navlist.item>
                <flux:navlist.item icon="building-office" :href="route('properties.index')" :current="request()->routeIs('properties.*')">
                    Elanlar
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Müştərilərim">
                <flux:navlist.item icon="users" :href="route('customers.index')" :current="request()->routeIs('customers.*')">
                    Müştərilərim <em class="text-[10px] font-normal not-italic text-zinc-400 italic">alıcılar</em>
                </flux:navlist.item>
                <flux:navlist.item icon="clipboard-document-list" :href="route('customer-requests.index')" :current="request()->routeIs('customer-requests.*')">
                    İstəklər
                </flux:navlist.item>
                <flux:navlist.item icon="check-badge" :href="route('matches.index')" :current="request()->routeIs('matches.*')">
                    Uyğunluqlar
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:navlist.item icon="arrow-right-start-on-rectangle" href="#" onclick="this.closest('form').submit()">
                    Çıxış
                </flux:navlist.item>
            </form>
        </flux:navlist>

        <div class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ auth()->user()->name }}</div>
            <div class="text-xs text-zinc-500">{{ ucfirst(auth()->user()->subscription_plan) }} plan</div>
        </div>
    </flux:sidebar>

    <flux:main>
        <div class="flex items-center gap-3 mb-6 lg:hidden">
            <flux:sidebar.toggle icon="bars-3" class="lg:hidden" />
            <span class="text-lg font-bold">Binokl.az</span>
        </div>

        {{ $slot }}
    </flux:main>
    @endauth

    @guest
        {{ $slot }}
    @endguest

    @fluxScripts
</body>
</html>

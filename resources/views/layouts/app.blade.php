<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Binokl.az' }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='50'>◉◉</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>
        .eye-char { display: inline-block; transition: transform 0.5s ease; }
    </style>
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900" style="position:relative;overflow-x:hidden;">

    {{-- Color washes --}}
    <div class="app-wash app-wash-1"></div>
    <div class="app-wash app-wash-2"></div>
    <div class="app-wash app-wash-3"></div>

    <style>
    .app-wash {
        position: fixed;
        border-radius: 50%;
        pointer-events: none;
        z-index: 0;
    }
    .app-wash-1 {
        width: 70vw; height: 70vw;
        background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 65%);
        top: -20vw; right: -15vw;
        animation: appDrift1 12s ease-in-out infinite alternate;
    }
    .app-wash-2 {
        width: 60vw; height: 60vw;
        background: radial-gradient(circle, rgba(14,165,233,0.10) 0%, transparent 65%);
        bottom: -15vw; left: -10vw;
        animation: appDrift2 16s ease-in-out infinite alternate;
    }
    .app-wash-3 {
        width: 50vw; height: 50vw;
        background: radial-gradient(circle, rgba(139,92,246,0.08) 0%, transparent 65%);
        bottom: 5vw; right: 5vw;
        animation: appDrift3 20s ease-in-out infinite alternate;
    }
    .dark .app-wash-1 { background: radial-gradient(circle, rgba(99,102,241,0.10) 0%, transparent 65%); }
    .dark .app-wash-2 { background: radial-gradient(circle, rgba(14,165,233,0.08) 0%, transparent 65%); }
    .dark .app-wash-3 { background: radial-gradient(circle, rgba(139,92,246,0.06) 0%, transparent 65%); }
    @keyframes appDrift1 {
        from { transform: translate(0, 0) scale(1); }
        to   { transform: translate(-3vw, 4vh) scale(1.06); }
    }
    @keyframes appDrift2 {
        from { transform: translate(0, 0) scale(1); }
        to   { transform: translate(4vw, -3vh) scale(1.05); }
    }
    @keyframes appDrift3 {
        from { transform: translate(0, 0) scale(1); }
        to   { transform: translate(-2vw, -4vh) scale(1.04); }
    }
    /* Bütün content z-index üstdə olsun */
    flux-sidebar, [data-flux-sidebar], .flux-main, [data-flux-main] {
        position: relative;
        z-index: 1;
    }
    </style>
    @auth
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 lg:w-64">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <div class="flex items-center gap-2 px-2 py-3">
            <span class="logo-light text-xl leading-none text-zinc-900 tracking-tighter select-none eye-logo"><span class="eye-char">◉</span><span class="eye-char">◉</span></span>
            <span class="logo-dark text-xl leading-none text-white tracking-tighter select-none eye-logo"><span class="eye-char">◎</span><span class="eye-char">◎</span></span>
            <a href="{{ route('dashboard') }}" class="text-lg font-bold text-zinc-800 dark:text-white">Binokl.az</a>
        </div>

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

            <flux:navlist.group heading="Müştərilərim">
                <flux:navlist.item
                    icon="users"
                    :icon:trailing="$hasRequests ? null : 'lock-closed'"
                    :href="route('customers.index')"
                    :current="request()->routeIs('customers.*')"
                >Müştərilərim <em class="text-[10px] font-normal text-zinc-400 italic">(alıcılar)</em></flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        @if(auth()->user()->hasRole('developer'))
        <flux:navlist variant="outline">
            <flux:navlist.group heading="Developer">
                <flux:navlist.item icon="document-text" :href="route('dev.logs')" :current="request()->routeIs('dev.logs')">
                    Loglar
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
        @endif

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
                Tariflər
            </flux:navlist.item>
        </flux:navlist>
        @endif

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="cog-6-tooth" :href="route('settings')" :current="request()->routeIs('settings')">
                Tənzimləmələr
            </flux:navlist.item>
        </flux:navlist>

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
            <span class="logo-light text-xl leading-none text-zinc-900 tracking-tighter select-none eye-logo"><span class="eye-char">◉</span><span class="eye-char">◉</span></span>
            <span class="logo-dark text-xl leading-none text-white tracking-tighter select-none eye-logo"><span class="eye-char">◎</span><span class="eye-char">◎</span></span>
            <a href="{{ route('dashboard') }}" class="text-lg font-bold">Binokl.az</a>
        </div>

        {{ $slot }}
    </flux:main>
    @endauth

    @guest
        {{ $slot }}
    @endguest

    @fluxScripts
    <script src="/socket.io.min.js"></script>
    <script>
        function blinkEye(el) {
            if (el.dataset.blinking) return;
            el.dataset.blinking = '1';
            el.style.transition = 'transform 0.5s ease';
            el.style.transform = 'rotateX(150deg)';
            setTimeout(() => {
                el.style.transform = 'rotateX(0deg)';
                setTimeout(() => {
                    el.style.transition = '';
                    el.style.transform = '';
                    delete el.dataset.blinking;
                }, 500);
            }, 500);
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.eye-logo').forEach(logo => {
                const [left, right] = logo.querySelectorAll('.eye-char');
                if (!left || !right) return;
                left.addEventListener('mouseenter', () => blinkEye(right));
                right.addEventListener('mouseenter', () => blinkEye(left));
            });
        });
        function flipRandomEye() {
            const chars = document.querySelectorAll('.eye-char');
            if (chars.length) blinkEye(chars[Math.floor(Math.random() * chars.length)]);
        }
        setInterval(flipRandomEye, 60000);
    </script>
</body>
</html>

<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $name = '';
    public string $phone = '';
    public string $telegramUserId = '';

    public function sessions(): \Illuminate\Support\Collection
    {
        $currentId = session()->getId();
        return DB::table('sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($currentId) {
                $ua = $s->user_agent ?? '';
                if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone')) {
                    $device = 'Mobil';
                    $icon = 'device-phone-mobile';
                } elseif (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) {
                    $device = 'Tablet';
                    $icon = 'device-tablet';
                } else {
                    $device = 'Kompüter';
                    $icon = 'computer-desktop';
                }

                if (str_contains($ua, 'Chrome')) $browser = 'Chrome';
                elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
                elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
                elseif (str_contains($ua, 'Edge')) $browser = 'Edge';
                else $browser = 'Brauzer';

                return (object) [
                    'id'         => $s->id,
                    'ip'         => $s->ip_address ?? '—',
                    'device'     => $device,
                    'icon'       => $icon,
                    'browser'    => $browser,
                    'logged_in_at' => ($t = cache()->get("session_login_time:{$s->id}"))
                        ? \Carbon\Carbon::createFromTimestamp($t)->format('d.m.Y H:i')
                        : '—',
                    'last_active'  => \Carbon\Carbon::createFromTimestamp($s->last_activity)->format('d.m.Y H:i'),
                    'is_current' => $s->id === $currentId,
                ];
            });
    }

    public function revokeSession(string $sessionId): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->delete();
    }

    public function revokeAllOtherSessions(): void
    {
        DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    public function mount(): void
    {
        $this->name           = auth()->user()->name;
        $this->phone          = auth()->user()->phone ?? '';
        $this->telegramUserId = auth()->user()->telegram_user_id ?? '';
    }

    public function testTelegram(): void
    {
        $user = auth()->user();
        if (!$user->telegram_user_id) {
            $this->dispatch('telegram-test-result', success: false, message: 'Telegram User ID daxil edilməyib.');
            return;
        }

        $telegram = new \App\Services\TelegramService();
        $telegram->send($user->telegram_user_id,
            "✅ <b>Test mesajı</b>\n\nBinokl.az bildirişləri aktiv və işləyir!"
        );

        $this->dispatch('telegram-test-result', success: true, message: 'Mesaj göndərildi!');
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name'           => 'required|min:2|max:255',
            'phone'          => 'nullable|max:20',
            'telegramUserId' => 'nullable|max:50',
        ]);

        $user = auth()->user();
        $oldTelegramId = $user->telegram_user_id;
        $newTelegramId = $this->telegramUserId ?: null;

        $user->update([
            'name'             => $this->name,
            'phone'            => $this->phone ?: null,
            'telegram_user_id' => $newTelegramId,
        ]);

        if ($newTelegramId && $newTelegramId !== $oldTelegramId) {
            $telegram = new \App\Services\TelegramService();
            $telegram->send($newTelegramId,
                "👋 <b>Xoş gəldiniz, {$user->name}!</b>\n\nBinokl.az bildirişləri uğurla qoşuldu. Yeni uyğunluq tapıldıqda sizə xəbər verəcəyik."
            );
        }

        $this->dispatch('profile-saved');
    }
}; ?>

<div x-data="{
        tab: new URLSearchParams(window.location.search).get('tab') || 'profile',
        setTab(val) {
            this.tab = val;
            const url = new URL(window.location);
            url.searchParams.set('tab', val);
            window.history.replaceState({}, '', url);
        }
     }" class="mx-auto max-w-[1600px]">
    <flux:heading size="xl" class="mb-6">Tənzimləmələr</flux:heading>

    <div class="flex gap-4 items-start">
    <div class="flex-1 min-w-0 max-w-4xl" style="margin-right: 18rem;">

    <div class="flex gap-6 items-start">

        {{-- Side nav --}}
        <div class="w-48 shrink-0 sticky top-4 self-start space-y-0.5">
            <button @click="setTab('profile')"
                    :class="tab === 'profile' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                    class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors text-left">
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                Profil məlumatları
            </button>
            <button @click="setTab('livefeed')"
                    :class="tab === 'livefeed' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                    class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors text-left">
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"/></svg>
                Canlı elanlar
            </button>
            <button @click="setTab('appearance')"
                    :class="tab === 'appearance' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                    class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors text-left">
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z"/></svg>
                Görünüş
            </button>
            <button @click="setTab('sessions')"
                    :class="tab === 'sessions' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                    class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition-colors text-left">
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/></svg>
                Aktiv cihazlar
            </button>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">

    {{-- ══════════════════════════════ PROFIL ══════════════════════════════ --}}
    <div x-show="tab === 'profile'">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
            <form wire:submit="saveProfile" class="space-y-4">
                <flux:input wire:model="name" label="Ad Soyad" required />
                <flux:input wire:model="phone" label="Telefon" placeholder="+994501234567"
                    x-on:input="
                        let val = $event.target.value.replace(/[^\d+]/g, '').replace(/^\++/, '');
                        if (val.startsWith('994')) val = '+' + val;
                        if (val.length > 20) val = val.slice(0, 20);
                        if ($event.target.value !== val) { $event.target.value = val; $wire.set('phone', val); }
                    " />

                {{-- Telegram --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <svg class="size-5 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Telegram bildirişləri</span>
                    </div>
                    <flux:card>
                        <flux:text>
                            Botun aktivləşdirilməsi qaydası :
                            <br><br>
                            1. Telegramda "@binoklaz_bot" yazaraq axtariş edin. Daha sonra @binoklaz_bot botuna daxil olub "start" düyməsini sıxın.
                            <br><br>
                            2. Telegramda "@userinfobot" yazaraq axtariş edin. Daha sonra @userinfobot botuna daxil olub "start" düyməsini sıxın, yoxdursa "/start" yazın
                            <br><br>
                            3. @userinfobot sizə  10 rəqəmli "Id" nömrəsi verəcək. Həmin "Id" nömrəsini aşağı yazıb yadda saxlayın
                        </flux:text>
                    </flux:card>
                    <flux:input wire:model="telegramUserId" label="Telegram User ID" placeholder="1234567890"/>
                    @if(auth()->user()->hasRole('developer'))
                    <div x-data="{ msg: '', ok: false }"
                         x-on:telegram-test-result.window="msg = $event.detail.message; ok = $event.detail.success; setTimeout(() => msg = '', 4000)"
                         class="flex items-center gap-3">
                        <flux:button wire:click="testTelegram" size="sm" variant="ghost" wire:loading.attr="disabled" wire:target="testTelegram">
                            <span wire:loading.remove wire:target="testTelegram">Test göndər</span>
                            <span wire:loading wire:target="testTelegram">Göndərilir...</span>
                        </flux:button>
                        <span x-show="msg" x-transition
                              :class="ok ? 'text-emerald-600' : 'text-red-500'"
                              class="text-sm" x-text="msg"></span>
                    </div>
                    @endif
                </div>

                <div class="flex items-center justify-between pt-1">
                    <span wire:loading wire:target="saveProfile" class="text-sm text-zinc-400">Saxlanılır...</span>
                    <div x-data="{ saved: false }"
                         x-on:profile-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
                         class="flex items-center gap-3 ml-auto">
                        <span x-show="saved" x-transition class="text-sm text-emerald-600">Saxlandı</span>
                        <flux:button type="submit" variant="primary" size="sm">Saxla</flux:button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════ CANLI ELANLAR ══════════════════════════════ --}}
    <div x-show="tab === 'livefeed'">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6"
             x-data="{
                 persist: localStorage.getItem('livefeed_persist') === 'true',
                 toggle(val) {
                     this.persist = val;
                     localStorage.setItem('livefeed_persist', val ? 'true' : 'false');
                     if (!val) localStorage.removeItem('livefeed_items');
                 }
             }">
            <p class="text-sm text-zinc-500 mb-5">Səhifə yeniləndikdə və ya dəyişdikdə canlı elanlar panelinin davranışını seçin</p>
            <div class="flex gap-2">
                <button type="button" @click="toggle(false)"
                        :class="!persist ? 'bg-indigo-50 border-indigo-400 text-indigo-700 dark:bg-indigo-950/50 dark:border-indigo-500 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-zinc-300'"
                        class="flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Sıfırlansın
                </button>
                <button type="button" @click="toggle(true)"
                        :class="persist ? 'bg-indigo-50 border-indigo-400 text-indigo-700 dark:bg-indigo-950/50 dark:border-indigo-500 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-zinc-300'"
                        class="flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition">
                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                    Qalsın
                </button>
            </div>
            <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                <span x-show="!persist">Hər dəfə səhifə yeniləndikdə canlı elanlar sıfırlanır.</span>
                <span x-show="persist">Son 14 elan yadda saxlanılır, səhifə yenilənsə belə görünür.</span>
            </p>
        </div>
    </div>

    {{-- ══════════════════════════════ GORUNUS ══════════════════════════════ --}}
    <div x-show="tab === 'appearance'">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
            <p class="text-sm text-zinc-500 mb-5">Tətbiqin rəng temasını seçin</p>
            <div x-data="{
                    theme: localStorage.getItem('flux.appearance') || 'system',
                    apply(val) {
                        this.theme = val;
                        if (val === 'dark') {
                            document.documentElement.classList.add('dark');
                            localStorage.setItem('flux.appearance', 'dark');
                        } else if (val === 'light') {
                            document.documentElement.classList.remove('dark');
                            localStorage.setItem('flux.appearance', 'light');
                        } else {
                            localStorage.removeItem('flux.appearance');
                            window.matchMedia('(prefers-color-scheme: dark)').matches
                                ? document.documentElement.classList.add('dark')
                                : document.documentElement.classList.remove('dark');
                        }
                    }
                 }"
                 class="flex gap-2">
                <template x-for="opt in [
                    { val: 'light',  label: 'Açıq',  icon: 'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z' },
                    { val: 'dark',   label: 'Tünd',  icon: 'M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z' },
                    { val: 'system', label: 'Sistem', icon: 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3' }
                ]" :key="opt.val">
                    <button type="button" @click="apply(opt.val)"
                            :class="theme === opt.val
                                ? 'bg-indigo-50 border-indigo-400 text-indigo-700 dark:bg-indigo-950/50 dark:border-indigo-500 dark:text-indigo-300'
                                : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600'"
                            class="flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition">
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="opt.icon" />
                        </svg>
                        <span x-text="opt.label"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════ AKTİV CİHAZLAR ══════════════════════════════ --}}
    <div x-show="tab === 'sessions'">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-zinc-500">Hesabınıza daxil olan aktiv cihazlar</p>
                @if($this->sessions()->where('is_current', false)->count() > 0)
                <flux:button wire:click="revokeAllOtherSessions" wire:confirm="Digər bütün cihazlardan çıxış olunacaq. Davam edilsin?" size="sm" variant="ghost" class="text-red-500 hover:text-red-600">
                    Digərlərindən çıx
                </flux:button>
                @endif
            </div>

            <div class="space-y-2">
                @foreach($this->sessions() as $s)
                <div class="flex items-center justify-between rounded-xl border border-zinc-100 dark:border-zinc-800 px-4 py-3 {{ $s->is_current ? 'bg-indigo-50/50 dark:bg-indigo-900/10 border-indigo-200 dark:border-indigo-800' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-lg flex items-center justify-center {{ $s->is_current ? 'bg-indigo-100 dark:bg-indigo-900/40' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                            @if($s->icon === 'device-phone-mobile')
                            <svg class="size-5 {{ $s->is_current ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3"/></svg>
                            @else
                            <svg class="size-5 {{ $s->is_current ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/></svg>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $s->device }} · {{ $s->browser }}</span>
                                @if($s->is_current)
                                <span class="text-xs bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded-md font-medium">Bu cihaz</span>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-400 mt-0.5">{{ $s->ip }} · Daxil olma: {{ $s->logged_in_at }} · Son aktivlik: {{ $s->last_active }}</div>
                        </div>
                    </div>
                    @if(!$s->is_current)
                    <flux:button wire:click="revokeSession('{{ $s->id }}')" wire:confirm="Bu cihazdan çıxış olunacaq. Davam edilsin?" size="sm" variant="ghost" class="text-zinc-400 hover:text-red-500 shrink-0">
                        Çıxış
                    </flux:button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

        </div>{{-- end content --}}
    </div>{{-- end settings flex --}}

    </div>{{-- end left --}}

    {{-- RIGHT: live feed panel --}}
    <div class="w-72 shrink-0 rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
         style="background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);
                position: fixed; top: 1rem; right: 1rem; bottom: 1rem;">
        @livewire('properties.live-feed', key('live-feed-settings'))
    </div>

    </div>{{-- end outer flex --}}
</div>

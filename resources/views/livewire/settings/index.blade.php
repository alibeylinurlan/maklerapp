<?php
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $phone = '';
    public string $telegramUserId = '';

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

        auth()->user()->update([
            'name'             => $this->name,
            'phone'            => $this->phone ?: null,
            'telegram_user_id' => $this->telegramUserId ?: null,
        ]);

        $this->dispatch('profile-saved');
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-8">
    <flux:heading size="xl">Tənzimləmələr</flux:heading>

    {{-- ── Ekran görünüşü ── --}}
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
        <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-100 mb-1">Ekran görünüşü</h2>
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

    {{-- ── Profil məlumatları ── --}}
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
        <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-100 mb-1">Profil məlumatları</h2>
        <p class="text-sm text-zinc-500 mb-5">Ad və telefon nömrənizi yeniləyin</p>

        <form wire:submit="saveProfile" class="space-y-4">
            <flux:input wire:model="name" label="Ad Soyad" required />
            <flux:input wire:model="phone" label="Telefon" placeholder="+994501234567"
                x-on:input="
                    let val = $event.target.value.replace(/[^\d+]/g, '').replace(/^\++/, '');
                    if (val.startsWith('994')) val = '+' + val;
                    if (val.length > 20) val = val.slice(0, 20);
                    if ($event.target.value !== val) { $event.target.value = val; $wire.set('phone', val); }
                " />
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Telegram bildirişləri</span>
                </div>
                <flux:input wire:model="telegramUserId" label="Telegram User ID" placeholder="123456789"
                    description="Öz Telegram User ID-nizi daxil edin. @userinfobot botundan əldə edə bilərsiniz." />
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

<?php

use App\Models\SubscriptionPlan;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        $activePlans = $user->userPlans()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('plan')
            ->get()
            ->filter(fn($up) => $up->plan !== null);

        $userPlansMap = [];
        foreach ($activePlans as $up) {
            $userPlansMap[$up->plan->key] = $up;
        }

        return [
            'plans'       => SubscriptionPlan::where('is_active', true)->orderBy('price')->get(),
            'userPlansMap' => $userPlansMap,
        ];
    }
}; ?>

<div>
<div class="mx-auto max-w-4xl px-4 py-8">

    {{-- Header --}}
    <div class="mb-10 text-center">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-600 shadow-lg shadow-indigo-500/30">
            <flux:icon.credit-card class="size-8 text-white" />
        </div>
        <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">Abunəlik paketləri</h1>
        <p class="mt-3 text-base text-zinc-500 dark:text-zinc-400">
            Paket aktivləşdirmək üçün administrator ilə əlaqə saxlayın
        </p>
    </div>

    {{-- Cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem;">
        @foreach($plans as $plan)
        @php
            $up = $userPlansMap[$plan->key] ?? null;
            $isActive = $up !== null;
            $icons = [
                'platform' => 'building-office',
                'requests' => 'clipboard-document-list',
            ];
            $icon = $icons[$plan->key] ?? 'star';
        @endphp

        <div style="border-radius: 1.25rem; overflow: hidden; box-shadow: 0 4px 24px 0 rgba(0,0,0,0.10);"
             class="flex flex-col border bg-white dark:bg-zinc-800 {{ $isActive ? 'border-indigo-400 dark:border-indigo-500' : 'border-zinc-200 dark:border-zinc-700' }}">

            {{-- Top accent bar --}}
            <div class="h-1.5 w-full {{ $isActive ? 'bg-indigo-500' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>

            <div class="flex flex-1 flex-col p-6">
                {{-- Icon --}}
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl {{ $isActive ? 'bg-indigo-100 dark:bg-indigo-900/40' : 'bg-zinc-100 dark:bg-zinc-700' }}">
                    <flux:icon :icon="$icon" class="size-6 {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
                </div>

                {{-- Name & desc --}}
                <div class="font-bold text-lg text-zinc-800 dark:text-white">{{ $plan->name_az }}</div>
                @if($plan->description_az)
                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">{{ $plan->description_az }}</div>
                @endif

                {{-- Price --}}
                <div class="mt-6 flex items-end gap-1">
                    <span class="text-4xl font-extrabold {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-800 dark:text-white' }}">
                        {{ number_format($plan->price, 0) }}
                    </span>
                    <span class="mb-1 text-lg font-semibold {{ $isActive ? 'text-indigo-500' : 'text-zinc-500' }}">₼</span>
                    <span class="mb-1 text-sm text-zinc-400">/ay</span>
                </div>

                {{-- Status --}}
                <div class="mt-auto pt-5">
                    @if($isActive)
                        <div class="flex items-center gap-2 rounded-xl bg-green-50 px-3 py-2 dark:bg-green-900/20">
                            <flux:icon.check-circle class="size-4 text-green-500" />
                            <div>
                                <div class="text-xs font-semibold text-green-700 dark:text-green-400">Aktiv</div>
                                <div class="text-xs text-green-600/70 dark:text-green-500/70">{{ $up->expires_at->format('d.m.Y') }}-ə qədər</div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-xl bg-zinc-100 px-3 py-2 dark:bg-zinc-700/50">
                            <flux:icon.lock-closed class="size-4 text-zinc-400" />
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Aktiv deyil</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Info --}}
    <div class="mt-8 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/40 dark:bg-amber-900/10">
        <flux:icon.information-circle class="mt-0.5 size-5 shrink-0 text-amber-500" />
        <p class="text-sm text-amber-700 dark:text-amber-400">
            Paket aktivləşdirmək üçün sistem administratoru ilə əlaqə saxlayın. Ödəniş təsdiqləndikdən sonra paket hesabınıza əlavə ediləcək.
        </p>
    </div>

</div>
</div>

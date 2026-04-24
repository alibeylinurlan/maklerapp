<?php

use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        $activePlanIds = $user
            ? $user->userPlans()->where('is_active', true)->where('expires_at', '>', now())->pluck('plan_id')->toArray()
            : [];

        $plans = SubscriptionPlan::orderBy('price')->get()->map(function ($plan) {
            $plan->feature_keys = DB::table('plan_features')
                ->where('plan_id', $plan->id)->pluck('feature_key')->toArray();
            return $plan;
        });

        return [
            'plans'          => $plans,
            'features'       => Feature::orderBy('sort_order')->get(),
            'activePlanIds'  => $activePlanIds,
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-10">

    {{-- Header --}}
    <div class="mb-12 text-center">
        <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">Tariflər</h1>
        <p class="mt-3 text-base text-zinc-500 dark:text-zinc-400">
            Tarif aktivləşdirmək üçün administrator ilə əlaqə saxlayın
        </p>
    </div>

    @php
    $colors = ['zinc', 'amber', 'indigo', 'violet', 'emerald', 'rose', 'sky', 'orange'];
    $colorMap = [
        'zinc'    => ['accent' => 'bg-zinc-500',   'badge' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',       'price' => 'text-zinc-800 dark:text-white',      'border' => 'border-zinc-200 dark:border-zinc-700'],
        'amber'   => ['accent' => 'bg-amber-400',  'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', 'price' => 'text-amber-600 dark:text-amber-400', 'border' => 'border-amber-200 dark:border-amber-800'],
        'indigo'  => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300', 'price' => 'text-indigo-600 dark:text-indigo-400', 'border' => 'border-indigo-300 dark:border-indigo-600'],
        'violet'  => ['accent' => 'bg-violet-500', 'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300', 'price' => 'text-violet-600 dark:text-violet-400', 'border' => 'border-violet-300 dark:border-violet-600'],
        'emerald' => ['accent' => 'bg-emerald-500','badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300','price' => 'text-emerald-600 dark:text-emerald-400','border' => 'border-emerald-300 dark:border-emerald-600'],
        'rose'    => ['accent' => 'bg-rose-500',   'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',     'price' => 'text-rose-600 dark:text-rose-400',   'border' => 'border-rose-300 dark:border-rose-600'],
        'sky'     => ['accent' => 'bg-sky-500',    'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',         'price' => 'text-sky-600 dark:text-sky-400',     'border' => 'border-sky-300 dark:border-sky-600'],
        'orange'  => ['accent' => 'bg-orange-500', 'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300','price' => 'text-orange-600 dark:text-orange-400','border' => 'border-orange-300 dark:border-orange-600'],
    ];
    @endphp

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4" style="perspective: 1200px;">
        @foreach($plans as $i => $plan)
        @php
            $colorKey = $colors[$i % count($colors)];
            $c = $colorMap[$colorKey];
            $isActive = in_array($plan->id, $activePlanIds);
        @endphp

        <div class="pricing-card relative flex flex-col rounded-2xl border {{ $c['border'] }} bg-white dark:bg-zinc-900 overflow-hidden shadow-sm"
             x-data="pricingCard()"
             x-on:mousemove="onMove($event, $el)"
             x-on:mouseleave="onLeave($el)"
             style="transform-style: preserve-3d; transition: transform 0.15s ease, box-shadow 0.15s ease; will-change: transform;">

            {{-- Top accent --}}
            <div class="h-1.5 w-full {{ $c['accent'] }}"></div>

            <div class="flex flex-1 flex-col p-5">

                {{-- Name --}}
                <div class="mb-4">
                    <span class="inline-block rounded-lg px-2.5 py-1 text-xs font-semibold {{ $c['badge'] }}">
                        {{ $plan->name_az }}
                    </span>
                </div>

                {{-- Price --}}
                <div class="mb-5 flex items-end gap-1">
                    <span class="text-4xl font-extrabold {{ $c['price'] }}">{{ number_format($plan->price, 0) }}</span>
                    <span class="mb-1 text-lg font-semibold text-zinc-400">₼</span>
                    <span class="mb-1 text-xs text-zinc-400">/ay</span>
                </div>

                {{-- Features --}}
                <ul class="flex-1 space-y-2.5 mb-6">
                    @foreach($features as $feature)
                    <li class="flex items-start gap-2">
                        @if(in_array($feature->key, $plan->feature_keys))
                            <svg class="mt-0.5 size-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $feature->name_az }}</span>
                        @else
                            <svg class="mt-0.5 size-4 shrink-0 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span class="text-sm text-zinc-400 dark:text-zinc-600">{{ $feature->name_az }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>

                {{-- Status / CTA --}}
                @if($isActive)
                    <div class="flex items-center gap-2 rounded-xl bg-green-50 dark:bg-green-900/20 px-3 py-2.5">
                        <svg class="size-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs font-semibold text-green-700 dark:text-green-400">Aktiv tarifiniz</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-2.5">
                        <svg class="size-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Admin ilə əlaqə saxlayın</span>
                    </div>
                @endif

            </div>
        </div>
        @endforeach
    </div>

    {{-- Contact info --}}
    <div class="mt-8 flex items-center gap-3 rounded-2xl border border-blue-100 dark:border-blue-900/40 bg-blue-50 dark:bg-blue-900/10 p-4">
        <svg class="size-5 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-blue-700 dark:text-blue-300">
            Tarif aktivləşdirmək üçün administrator ilə əlaqə saxlayın. Ödəniş təsdiqləndikdən sonra tarif hesabınıza dərhal əlavə ediləcək.
        </p>
    </div>

</div>

<style>
.pricing-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: radial-gradient(circle at var(--mx, 50%) var(--my, 50%), rgba(255,255,255,0.08) 0%, transparent 60%);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
}
.pricing-card:hover::after {
    opacity: 1;
}
.dark .pricing-card::after {
    background: radial-gradient(circle at var(--mx, 50%) var(--my, 50%), rgba(255,255,255,0.05) 0%, transparent 60%);
}
</style>

<script>
function pricingCard() {
    return {
        onMove(e, el) {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const cx = rect.width / 2;
            const cy = rect.height / 2;
            const rotateX = ((y - cy) / cy) * -8;
            const rotateY = ((x - cx) / cx) * 8;
            el.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.03,1.03,1.03)`;
            el.style.boxShadow = `${-rotateY * 1.5}px ${rotateX * 1.5}px 32px rgba(0,0,0,0.12)`;
            el.style.setProperty('--mx', `${(x / rect.width) * 100}%`);
            el.style.setProperty('--my', `${(y / rect.height) * 100}%`);
        },
        onLeave(el) {
            el.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale3d(1,1,1)';
            el.style.boxShadow = '';
        }
    }
}
</script>

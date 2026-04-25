<?php

use App\Models\Feature;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    // Plan editing
    public ?int $editingPlanId = null;
    public string $planName = '';
    public string $planDescription = '';
    public string $planPrice = '';
    public bool $showPlanForm = false;

    // Feature editing
    public ?int $editingFeaturesForPlanId = null;
    public array $selectedFeatureKeys = [];

    // Feature name editing
    public ?int $editingFeatureNameId = null;
    public string $editingFeatureName = '';

    public function startEditFeatureName(int $featureId): void
    {
        $f = Feature::findOrFail($featureId);
        $this->editingFeatureNameId = $featureId;
        $this->editingFeatureName = $f->name_az;
    }

    public function saveFeatureName(): void
    {
        $name = trim($this->editingFeatureName);
        if (!$name) return;
        Feature::where('id', $this->editingFeatureNameId)->update(['name_az' => $name]);
        $this->editingFeatureNameId = null;
        $this->editingFeatureName = '';
    }

    public function cancelEditFeatureName(): void
    {
        $this->editingFeatureNameId = null;
        $this->editingFeatureName = '';
    }

    // Feature reordering
    public function moveFeature(int $featureId, string $direction): void
    {
        $features = Feature::orderBy('sort_order')->get();
        $index = $features->search(fn($f) => $f->id === $featureId);
        if ($index === false) return;

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapIndex < 0 || $swapIndex >= $features->count()) return;

        $a = $features[$index];
        $b = $features[$swapIndex];
        [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];
        $a->update(['sort_order' => $bOrder]);
        $b->update(['sort_order' => $aOrder]);
    }

    public function editFeatures(int $planId): void
    {
        $this->editingFeaturesForPlanId = $planId;
        $this->selectedFeatureKeys = DB::table('plan_features')
            ->where('plan_id', $planId)
            ->pluck('feature_key')
            ->toArray();
    }

    public function saveFeatures(): void
    {
        DB::table('plan_features')->where('plan_id', $this->editingFeaturesForPlanId)->delete();
        foreach ($this->selectedFeatureKeys as $key) {
            DB::table('plan_features')->insert([
                'plan_id'     => $this->editingFeaturesForPlanId,
                'feature_key' => $key,
            ]);
        }
        // Feature cache-ini təmizlə
        $userIds = UserPlan::where('plan_id', $this->editingFeaturesForPlanId)
            ->where('is_active', true)->where('expires_at', '>', now())
            ->pluck('user_id');
        foreach ($userIds as $uid) {
            cache()->forget("user_features:{$uid}");
        }
        $this->editingFeaturesForPlanId = null;
        $this->selectedFeatureKeys = [];
    }

    // User subscription modal
    public bool $showSubForm = false;
    public ?int $selectedUserId = null;
    public array $selectedPlanIds = [];
    public string $expiresAt = '';

    public function editPlan(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->planName = $plan->name_az;
        $this->planDescription = $plan->description_az ?? '';
        $this->planPrice = (string) $plan->price;
        $this->showPlanForm = true;
    }

    public function savePlan(): void
    {
        $this->validate([
            'planPrice' => 'required|numeric|min:0',
            'planName'  => 'required|min:2|max:100',
        ]);

        SubscriptionPlan::where('id', $this->editingPlanId)->update([
            'name_az'        => $this->planName,
            'description_az' => $this->planDescription ?: null,
            'price'          => $this->planPrice,
        ]);

        $this->showPlanForm = false;
        $this->reset(['editingPlanId', 'planName', 'planDescription', 'planPrice']);
    }

    public function openSubForm(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->selectedPlanIds = [];
        $this->expiresAt = now()->addMonth()->format('Y-m-d');
        $this->showSubForm = true;
    }

    public function assignPlans(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'expiresAt'      => 'required|date|after:today',
            'selectedPlanIds' => 'array|min:1',
        ]);

        foreach ($this->selectedPlanIds as $planId) {
            // Deactivate existing active plan of same type for this user
            UserPlan::where('user_id', $this->selectedUserId)
                ->where('plan_id', $planId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            UserPlan::create([
                'user_id'     => $this->selectedUserId,
                'plan_id'     => $planId,
                'starts_at'   => now(),
                'expires_at'  => $this->expiresAt . ' 23:59:59',
                'assigned_by' => auth()->id(),
                'is_active'   => true,
            ]);
        }

        cache()->forget("user_features:{$this->selectedUserId}");
        $this->showSubForm = false;
        $this->reset(['selectedUserId', 'selectedPlanIds', 'expiresAt']);
    }

    public function revokePlan(int $userPlanId): void
    {
        $up = UserPlan::find($userPlanId);
        $up?->update(['is_active' => false]);
        if ($up) cache()->forget("user_features:{$up->user_id}");
    }

    public function with(): array
    {
        $users = User::with([
            'userPlans' => fn($q) => $q->where('is_active', true)
                ->where('expires_at', '>', now())
                ->with('plan')
                ->orderBy('expires_at'),
        ])->orderBy('name')->get();

        $plans = SubscriptionPlan::orderBy('price')->get()->each(function ($plan) {
            $plan->feature_keys = DB::table('plan_features')
                ->where('plan_id', $plan->id)->pluck('feature_key')->toArray();
        });

        return [
            'plans'    => $plans,
            'features' => Feature::orderBy('sort_order')->get(),
            'users'    => $users,
        ];
    }
}; ?>

<div>
    <flux:heading size="xl">Abunəlik planları</flux:heading>

    {{-- Plans list --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach($plans as $plan)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-semibold text-zinc-800 dark:text-white">{{ $plan->name_az }}</div>
                    @if($plan->description_az)
                    <div class="mt-0.5 text-xs text-zinc-500">{{ $plan->description_az }}</div>
                    @endif
                </div>
                <flux:button wire:click="editPlan({{ $plan->id }})" size="xs" variant="ghost" icon="pencil-square" />
            </div>
            <div class="mt-3 text-2xl font-bold text-indigo-600">
                {{ number_format($plan->price, 2) }} <span class="text-base font-normal text-zinc-500">₼/ay</span>
            </div>
            <div class="mt-3 space-y-1.5">
                @foreach($features as $f)
                <div class="flex items-center gap-2">
                    @if(in_array($f->key, $plan->feature_keys))
                        <svg class="size-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $f->name_az }}</span>
                    @else
                        <svg class="size-3.5 text-zinc-300 dark:text-zinc-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span class="text-xs text-zinc-400 line-through">{{ $f->name_az }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-zinc-400">
                    {{ $users->filter(fn($u) => $u->userPlans->where('plan_id', $plan->id)->count() > 0)->count() }} aktiv istifadəçi
                </span>
                <flux:button wire:click="editFeatures({{ $plan->id }})" size="xs" variant="ghost">
                    Funksiyalar
                </flux:button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Feature edit modal --}}
    @if($editingFeaturesForPlanId)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background:rgba(0,0,0,0.5)">
        <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-800 dark:text-white mb-4">
                {{ $plans->firstWhere('id', $editingFeaturesForPlanId)?->name_az }} — Funksiyalar
            </h3>
            <div class="space-y-2">
                @foreach($features as $f)
                <label class="flex items-center gap-3 cursor-pointer rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <input type="checkbox"
                           wire:model="selectedFeatureKeys"
                           value="{{ $f->key }}"
                           class="size-4 rounded accent-indigo-500" />
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $f->name_az }}</span>
                </label>
                @endforeach
            </div>
            <div class="mt-5 flex gap-2 justify-end">
                <flux:button wire:click="$set('editingFeaturesForPlanId', null)" variant="ghost" size="sm">Ləğv et</flux:button>
                <flux:button wire:click="saveFeatures" variant="primary" size="sm">Saxla</flux:button>
            </div>
        </div>
    </div>
    @endif

    {{-- Feature ordering --}}
    <div class="mt-8 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-sm max-w-sm">
        <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-3">Funksiyaların sırası</div>
        <div class="space-y-1">
            @foreach($features as $i => $f)
            <div class="rounded-lg px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                @if($editingFeatureNameId === $f->id)
                <div class="flex items-center gap-1.5">
                    <input wire:model="editingFeatureName"
                           wire:keydown.enter="saveFeatureName"
                           wire:keydown.escape="cancelEditFeatureName"
                           class="flex-1 rounded-lg border border-indigo-400 bg-white dark:bg-zinc-700 px-2 py-1 text-sm text-zinc-800 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                           autofocus />
                    <button wire:click="saveFeatureName" class="size-6 flex items-center justify-center rounded text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    </button>
                    <button wire:click="cancelEditFeatureName" class="size-6 flex items-center justify-center rounded text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @else
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-400 w-5 text-right shrink-0">{{ $i + 1 }}</span>
                    <span class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $f->name_az }}</span>
                    <div class="flex gap-1 shrink-0">
                        <button wire:click="startEditFeatureName({{ $f->id }})"
                            class="size-6 flex items-center justify-center rounded text-zinc-300 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        </button>
                        <button wire:click="moveFeature({{ $f->id }}, 'up')"
                            @if($i === 0) disabled @endif
                            class="size-6 flex items-center justify-center rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                        </button>
                        <button wire:click="moveFeature({{ $f->id }}, 'down')"
                            @if($i === $features->count() - 1) disabled @endif
                            class="size-6 flex items-center justify-center rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Users & subscriptions --}}
    <div class="mt-8 flex items-center justify-between">
        <flux:heading size="lg">İstifadəçilərin abunəlikləri</flux:heading>
    </div>

    <flux:table class="mt-3">
        <flux:table.columns>
            <flux:table.column>İstifadəçi</flux:table.column>
            <flux:table.column>Aktiv tariflər</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($users as $user)
            <flux:table.row>
                <flux:table.cell>
                    <div class="font-medium text-sm">{{ $user->name }}</div>
                    <div class="text-xs text-zinc-400">{{ $user->email }}</div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap gap-1">
                        @forelse($user->userPlans as $up)
                            <div class="flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                <span>{{ $up->plan->name_az }}</span>
                                <span class="text-indigo-400">· {{ $up->expires_at->format('d.m.Y') }}</span>
                                <button wire:click="revokePlan({{ $up->id }})" wire:confirm="Bu tarifi ləğv etmək istəyirsiniz?"
                                    class="ml-0.5 text-indigo-400 hover:text-red-500 transition">×</button>
                            </div>
                        @empty
                            <span class="text-xs text-zinc-400">—</span>
                        @endforelse
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:button wire:click="openSubForm({{ $user->id }})" size="xs" variant="ghost" icon="plus">Tarif əlavə et</flux:button>
                </flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Plan edit modal --}}
    <flux:modal wire:model="showPlanForm" class="max-w-sm">
        <flux:heading>Planı redaktə et</flux:heading>
        <form wire:submit="savePlan" class="mt-4 space-y-4">
            <flux:input wire:model="planName" label="Ad" required />
            <flux:input wire:model="planDescription" label="Açıqlama" />
            <flux:input wire:model="planPrice" label="Qiymət (₼/ay)" type="number" step="0.01" min="0" required />
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showPlanForm', false)" variant="ghost">Ləğv et</flux:button>
                <flux:button type="submit" variant="primary">Saxla</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Assign subscription modal --}}
    <flux:modal wire:model="showSubForm" class="max-w-md">
        <flux:heading>Tarif əlavə et</flux:heading>
        <form wire:submit="assignPlans" class="mt-4 space-y-4">
            <div>
                <flux:label>Tarif(lər)</flux:label>
                <div class="mt-2 space-y-2">
                    @foreach($plans as $plan)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 p-2.5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                        <input type="checkbox" value="{{ $plan->id }}" wire:model="selectedPlanIds"
                            class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                        <div class="flex-1">
                            <div class="text-sm font-medium text-zinc-800 dark:text-white">{{ $plan->name_az }}</div>
                            <div class="text-xs text-zinc-500">{{ number_format($plan->price, 2) }} ₼/ay</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('selectedPlanIds') <div class="mt-1 text-xs text-red-500">{{ $message }}</div> @enderror
            </div>

            <flux:input wire:model="expiresAt" label="Bitmə tarixi" type="date" required />

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showSubForm', false)" variant="ghost">Ləğv et</flux:button>
                <flux:button type="submit" variant="primary">Əlavə et</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

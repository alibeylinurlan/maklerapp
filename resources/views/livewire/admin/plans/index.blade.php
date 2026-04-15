<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserPlan;
use Livewire\Volt\Component;

new class extends Component {
    // Plan editing
    public ?int $editingPlanId = null;
    public string $planName = '';
    public string $planDescription = '';
    public string $planPrice = '';
    public bool $showPlanForm = false;

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

        $this->showSubForm = false;
        $this->reset(['selectedUserId', 'selectedPlanIds', 'expiresAt']);
    }

    public function revokePlan(int $userPlanId): void
    {
        UserPlan::where('id', $userPlanId)->update(['is_active' => false]);
    }

    public function with(): array
    {
        $users = User::with([
            'userPlans' => fn($q) => $q->where('is_active', true)
                ->where('expires_at', '>', now())
                ->with('plan')
                ->orderBy('expires_at'),
        ])->orderBy('name')->get();

        return [
            'plans' => SubscriptionPlan::orderBy('price')->get(),
            'users' => $users,
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
            <div class="mt-2 text-xs text-zinc-400">
                {{ $users->filter(fn($u) => $u->userPlans->where('plan_id', $plan->id)->count() > 0)->count() }} aktiv istifadəçi
            </div>
        </div>
        @endforeach
    </div>

    {{-- Users & subscriptions --}}
    <div class="mt-8 flex items-center justify-between">
        <flux:heading size="lg">İstifadəçilərin abunəlikləri</flux:heading>
    </div>

    <flux:table class="mt-3">
        <flux:table.columns>
            <flux:table.column>İstifadəçi</flux:table.column>
            <flux:table.column>Aktiv paketlər</flux:table.column>
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
                                <button wire:click="revokePlan({{ $up->id }})" wire:confirm="Bu paketi ləğv etmək istəyirsiniz?"
                                    class="ml-0.5 text-indigo-400 hover:text-red-500 transition">×</button>
                            </div>
                        @empty
                            <span class="text-xs text-zinc-400">—</span>
                        @endforelse
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:button wire:click="openSubForm({{ $user->id }})" size="xs" variant="ghost" icon="plus">Paket əlavə et</flux:button>
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
        <flux:heading>Paket əlavə et</flux:heading>
        <form wire:submit="assignPlans" class="mt-4 space-y-4">
            <div>
                <flux:label>Paket(lər)</flux:label>
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

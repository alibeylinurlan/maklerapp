<?php

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserPlan;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    // Form
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public array $selectedRoles = [];

    public string $parentId = '';

    // Plan assignment
    public array $newPlanIds = [];
    public string $newPlanExpires = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'password', 'selectedRoles', 'parentId']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->password = '';
        $this->selectedRoles = $user->roles->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $this->parentId = (string) ($user->parent_id ?? '');
        $this->newPlanIds = [];
        $this->newPlanExpires = now()->addMonth()->format('Y-m-d');
        $this->showForm = true;
    }

    public function assignPlans(): void
    {
        $this->validate([
            'newPlanIds'     => 'array|min:1',
            'newPlanExpires' => 'required|date|after:today',
        ]);

        foreach ($this->newPlanIds as $planId) {
            UserPlan::where('user_id', $this->editingId)
                ->where('plan_id', $planId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            UserPlan::create([
                'user_id'     => $this->editingId,
                'plan_id'     => $planId,
                'starts_at'   => now(),
                'expires_at'  => $this->newPlanExpires . ' 23:59:59',
                'assigned_by' => auth()->id(),
                'is_active'   => true,
            ]);
        }

        $this->reset(['newPlanIds', 'newPlanExpires']);
        $this->newPlanExpires = now()->addMonth()->format('Y-m-d');
    }

    public function revokePlan(int $userPlanId): void
    {
        UserPlan::where('id', $userPlanId)->update(['is_active' => false]);
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingId,
            'phone' => 'nullable|max:20',
            'selectedRoles' => 'array',
        ];

        if (!$this->editingId) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
        ];

        if ($this->password) {
            $data['password'] = $this->password;
        }

        $data['parent_id'] = $this->parentId ?: null;

        $user = User::updateOrCreate(['id' => $this->editingId], $data);

        $roleNames = Role::whereIn('id', $this->selectedRoles)->pluck('name')->toArray();
        $user->syncRoles($roleNames);

        $this->showForm = false;
        $this->reset(['editingId', 'name', 'email', 'phone', 'password', 'selectedRoles', 'parentId']);
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) return;
        $user->delete();
    }

    public function removeRole(string $roleId): void
    {
        $this->selectedRoles = array_values(array_diff($this->selectedRoles, [$roleId]));
    }

    public function with(): array
    {
        $query = User::with(['roles', 'parent:id,name', 'userPlans' => fn($q) => $q->where('is_active', true)->where('expires_at', '>', now())->with('plan')])
            ->withCount('customers')
            ->orderByDesc('created_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        return [
            'users' => $query->paginate(20),
            'roles' => Role::orderBy('name')->get(),
            'allUsers' => User::orderBy('name')->get(['id', 'name']),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price')->get(),
            'editingUserPlans' => $this->editingId
                ? UserPlan::where('user_id', $this->editingId)->where('is_active', true)->where('expires_at', '>', now())->with('plan')->orderBy('expires_at')->get()
                : collect(),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between">
        <flux:heading size="xl">İstifadəçilər</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus" size="sm">Yeni istifadəçi</flux:button>
    </div>

    <div class="mt-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Ad və ya email axtar..." icon="magnifying-glass" class="max-w-xs" />
    </div>

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>ID</flux:table.column>
            <flux:table.column>Ad</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Telefon</flux:table.column>
            <flux:table.column>Rollar</flux:table.column>
            <flux:table.column>Parent</flux:table.column>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column>Müştəri</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Qeydiyyat</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($users as $user)
            <flux:table.row>
                <flux:table.cell class="text-xs text-zinc-400">{{ $user->id }}</flux:table.cell>
                <flux:table.cell class="font-medium">{{ $user->name }}</flux:table.cell>
                <flux:table.cell class="text-sm">{{ $user->email }}</flux:table.cell>
                <flux:table.cell class="text-sm">{{ $user->phone ?? '-' }}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap gap-1">
                        @foreach($user->roles as $role)
                            <flux:badge size="sm" color="{{ $role->name === 'superadmin' ? 'red' : ($role->name === 'admin' ? 'amber' : 'blue') }}">
                                {{ $role->name }}
                            </flux:badge>
                        @endforeach
                    </div>
                </flux:table.cell>
                <flux:table.cell class="text-xs text-zinc-500">
                    {{ $user->parent?->name ?? '—' }}
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap gap-1">
                        @forelse($user->userPlans as $up)
                            <div class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ $up->plan->name_az }}
                                <span class="text-indigo-400">· {{ $up->expires_at->format('d.m.Y') }}</span>
                            </div>
                        @empty
                            <span class="text-xs text-zinc-400">—</span>
                        @endforelse
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ $user->customers_count }}</flux:table.cell>
                <flux:table.cell>
                    <flux:button wire:click="toggleActive({{ $user->id }})" size="xs" variant="ghost">
                        @if($user->is_active)
                            <flux:badge color="green" size="sm">Aktiv</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Deaktiv</flux:badge>
                        @endif
                    </flux:button>
                </flux:table.cell>
                <flux:table.cell class="text-xs text-zinc-500">{{ $user->created_at->format('d.m.Y') }}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex gap-1">
                        <flux:button wire:click="edit({{ $user->id }})" size="xs" variant="ghost" icon="pencil-square" />
                        @if($user->id !== auth()->id())
                        <flux:button wire:click="deleteUser({{ $user->id }})" wire:confirm="Bu istifadəçini silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                        @endif
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $users->links() }}</div>

    {{-- User form modal --}}
    <flux:modal wire:model="showForm" class="max-w-lg">
        <flux:heading>{{ $editingId ? 'İstifadəçi redaktə' : 'Yeni istifadəçi' }}</flux:heading>
        <form wire:submit="save" class="mt-4 space-y-4">
            <flux:input wire:model="name" label="Ad Soyad" required />
            <flux:input wire:model="email" label="Email" type="email" required />
            <flux:input wire:model="phone" label="Telefon" />
            <flux:input wire:model="password" label="{{ $editingId ? 'Şifrə (boş buraxsan dəyişməz)' : 'Şifrə' }}" type="password" />

            <div>
                <flux:heading size="sm" class="mb-1">Rollar</flux:heading>
                @if(!empty($selectedRoles))
                <div class="mb-2 flex flex-wrap gap-1">
                    @foreach($selectedRoles as $roleId)
                        @php($role = $roles->firstWhere('id', $roleId))
                        @if($role)
                        <flux:badge size="sm" color="{{ $role->name === 'superadmin' ? 'red' : ($role->name === 'admin' ? 'amber' : 'blue') }}">
                            {{ $role->name }}
                            <flux:badge.close wire:click="removeRole('{{ $roleId }}')" class="cursor-pointer" />
                        </flux:badge>
                        @endif
                    @endforeach
                </div>
                @endif

                <div x-data="{
                    open: false,
                    roles: {{ Js::from($roles->map(fn($r) => ['id' => (string)$r->id, 'name' => $r->name])) }},
                    toggle(id) {
                        const ids = [...$wire.selectedRoles];
                        const idx = ids.indexOf(id);
                        if (idx > -1) { ids.splice(idx, 1); } else { ids.push(id); }
                        $wire.set('selectedRoles', ids);
                    },
                    isSelected(id) { return $wire.selectedRoles.includes(id); }
                }" class="relative">
                    <button type="button" @click="open = !open"
                        class="relative flex h-9 w-full items-center justify-between rounded-md border border-zinc-200 bg-white pl-3 pr-8 text-left text-sm shadow-xs outline-none transition hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800"
                    >
                        <span class="text-zinc-400 text-sm" x-text="$wire.selectedRoles.length ? $wire.selectedRoles.length + ' rol seçilib' : 'Rol seçin...'"></span>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <flux:icon.chevron-up-down class="size-4 text-zinc-400" />
                        </span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition.opacity
                        class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
                    >
                        <template x-for="role in roles" :key="role.id">
                            <button type="button" @click.stop="toggle(role.id)"
                                :class="isSelected(role.id) ? 'bg-zinc-100 dark:bg-zinc-700' : 'hover:bg-zinc-50 dark:hover:bg-zinc-700/50'"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition"
                            >
                                <span :class="isSelected(role.id) ? 'bg-indigo-600 border-indigo-600' : 'border-zinc-300 dark:border-zinc-500'"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition"
                                >
                                    <svg x-show="isSelected(role.id)" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                <span x-text="role.name" class="text-zinc-700 dark:text-zinc-300"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <flux:select wire:model="parentId" label="Üst makler (parent)" size="sm">
                    <flux:select.option value="">— Yoxdur —</flux:select.option>
                    @foreach($allUsers as $u)
                        @if($u->id !== $editingId)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
            </div>

            @if($editingId)
            <div class="border-t border-zinc-100 pt-4 dark:border-zinc-700">
                <flux:heading size="sm" class="mb-2">Paketlər</flux:heading>

                {{-- Active plans --}}
                @if($editingUserPlans->isNotEmpty())
                <div class="mb-3 space-y-1.5">
                    @foreach($editingUserPlans as $up)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-700/40">
                        <div>
                            <span class="text-sm font-medium text-zinc-800 dark:text-white">{{ $up->plan->name_az }}</span>
                            <span class="ml-2 text-xs text-zinc-400">{{ $up->expires_at->format('d.m.Y') }}-ə qədər</span>
                        </div>
                        <button wire:click="revokePlan({{ $up->id }})" wire:confirm="Bu paketi ləğv etmək istəyirsiniz?"
                            class="text-xs text-red-400 hover:text-red-600 transition">Ləğv et</button>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Assign new plans --}}
                <div class="rounded-lg border border-dashed border-zinc-200 p-3 dark:border-zinc-600">
                    <div class="mb-2 text-xs font-medium text-zinc-500">Yeni paket əlavə et</div>
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach($plans as $plan)
                        <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                            <input type="checkbox" value="{{ $plan->id }}" wire:model="newPlanIds"
                                class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $plan->name_az }}</span>
                            <span class="text-xs text-zinc-400">{{ number_format($plan->price, 0) }}₼</span>
                        </label>
                        @endforeach
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:input wire:model="newPlanExpires" type="date" label="Bitmə tarixi" size="sm" />
                        </div>
                        <flux:button wire:click="assignPlans" size="sm" variant="ghost" icon="plus">Əlavə et</flux:button>
                    </div>
                </div>
            </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showForm', false)" variant="ghost">Ləğv et</flux:button>
                <flux:button type="submit" variant="primary">Saxla</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

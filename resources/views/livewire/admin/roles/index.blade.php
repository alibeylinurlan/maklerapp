<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $roleName = '';
    public array $selectedPermissions = [];

    public function create(): void
    {
        $this->reset(['editingId', 'roleName', 'selectedPermissions']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->editingId = $role->id;
        $this->roleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'roleName' => 'required|min:2|max:50',
        ]);

        $role = $this->editingId
            ? Role::findOrFail($this->editingId)
            : Role::create(['name' => $this->roleName]);

        if ($this->editingId) {
            $role->update(['name' => $this->roleName]);
        }

        $role->syncPermissions($this->selectedPermissions);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->showForm = false;
        $this->reset(['editingId', 'roleName', 'selectedPermissions']);
    }

    public function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);
        if ($role->name === 'superadmin') return;
        $role->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function createPermission(): void
    {
        $name = trim($this->newPermission ?? '');
        if ($name) {
            Permission::firstOrCreate(['name' => $name]);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->newPermission = '';
        }
    }

    public string $newPermission = '';

    public function togglePermission(string $perm): void
    {
        if (in_array($perm, $this->selectedPermissions)) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, [$perm]));
        } else {
            $this->selectedPermissions[] = $perm;
        }
    }

    public function selectAllPermissions(): void
    {
        $this->selectedPermissions = Permission::pluck('name')->toArray();
    }

    public function deselectAllPermissions(): void
    {
        $this->selectedPermissions = [];
    }

    public function with(): array
    {
        return [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Rollar və İcazələr</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus" size="sm">Yeni rol</flux:button>
    </div>

    {{-- Rollar siyahısı --}}
    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>Rol</flux:table.column>
            <flux:table.column>İcazələr</flux:table.column>
            <flux:table.column>İstifadəçilər</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($roles as $role)
            <flux:table.row>
                <flux:table.cell>
                    <flux:badge color="{{ $role->name === 'superadmin' ? 'red' : ($role->name === 'admin' ? 'amber' : 'blue') }}">
                        {{ $role->name }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>
                    @if($role->name === 'superadmin')
                        <span class="text-xs text-zinc-500">Bütün icazələr (bypass)</span>
                    @else
                        <span class="text-sm">{{ $role->permissions_count }}</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex gap-1">
                        <flux:button wire:click="edit({{ $role->id }})" size="xs" variant="ghost" icon="pencil-square" />
                        @if(!in_array($role->name, ['superadmin']))
                        <flux:button wire:click="deleteRole({{ $role->id }})" wire:confirm="Bu rolu silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                        @endif
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Bütün icazələr (qruplaşdırılmış) --}}
    <flux:heading size="lg" class="mt-8">Mövcud icazələr</flux:heading>
    <div class="mt-3 space-y-3">
        @php
            $grouped = $permissions->groupBy(fn($p) => explode('.', $p->name)[0]);
        @endphp
        @foreach($grouped as $group => $perms)
        <div>
            <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ $group }}</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($perms as $perm)
                    <flux:badge size="sm" color="zinc">{{ $perm->name }}</flux:badge>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-3 flex items-end gap-2">
        <div class="w-64">
            <flux:input wire:model="newPermission" placeholder="Yeni icazə (məs: reports.read)" size="sm" />
        </div>
        <flux:button wire:click="createPermission" size="sm" variant="ghost" icon="plus">Əlavə et</flux:button>
    </div>

    {{-- Rol form modal --}}
    <flux:modal wire:model="showForm" class="max-w-2xl">
        <flux:heading>{{ $editingId ? 'Rolu redaktə et' : 'Yeni rol' }}</flux:heading>
        <form wire:submit="save" class="mt-4 space-y-4">
            <flux:input wire:model="roleName" label="Rol adı" required />

            <div>
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">İcazələr</flux:heading>
                    <div class="flex gap-2">
                        <button type="button" wire:click="selectAllPermissions" class="text-xs text-indigo-600 hover:underline">Hamısını seç</button>
                        <button type="button" wire:click="deselectAllPermissions" class="text-xs text-red-500 hover:underline">Hamısını sil</button>
                    </div>
                </div>

                <div class="mt-2 max-h-72 space-y-3 overflow-y-auto rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                    @php
                        $groupedPerms = $permissions->groupBy(fn($p) => explode('.', $p->name)[0]);
                    @endphp
                    @foreach($groupedPerms as $group => $perms)
                    <div>
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ $group }}</div>
                        <div class="grid grid-cols-2 gap-1">
                            @foreach($perms as $perm)
                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <input
                                    type="checkbox"
                                    value="{{ $perm->name }}"
                                    @if(in_array($perm->name, $selectedPermissions)) checked @endif
                                    wire:click="togglePermission('{{ $perm->name }}')"
                                    class="rounded border-zinc-300 text-indigo-600"
                                />
                                <span class="text-zinc-700 dark:text-zinc-300">{{ explode('.', $perm->name)[1] ?? $perm->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-1 text-xs text-zinc-500">{{ count($selectedPermissions) }} icazə seçilib</div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showForm', false)" variant="ghost">Ləğv et</flux:button>
                <flux:button type="submit" variant="primary">Saxla</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

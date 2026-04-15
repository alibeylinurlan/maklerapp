<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Property;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $categoryId = '';
    #[Url] public array $locationIds = [];
    #[Url] public string $rooms = '';
    #[Url] public string $priceMin = '';
    #[Url] public string $priceMax = '';
    #[Url] public string $floorMin = '';
    #[Url] public string $floorMax = '';
    #[Url] public string $areaMin = '';
    #[Url] public string $areaMax = '';
    #[Url] public bool $hasMortgage = false;
    #[Url] public bool $hasBillOfSale = false;
    #[Url] public bool $notFirstFloor = false;
    #[Url] public bool $notTopFloor = false;
    #[Url] public bool $onlyTopFloor = false;

    public function filter(): void
    {
        $this->resetPage();
    }

    public function removeLocation(string $id): void
    {
        $this->locationIds = array_values(array_diff($this->locationIds, [$id]));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'locationIds', 'rooms', 'priceMin', 'priceMax',
            'floorMin', 'floorMax', 'areaMin', 'areaMax', 'hasMortgage', 'hasBillOfSale',
            'notFirstFloor', 'notTopFloor', 'onlyTopFloor']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->search || $this->categoryId || !empty($this->locationIds) || $this->rooms
            || $this->priceMin || $this->priceMax || $this->floorMin || $this->floorMax
            || $this->areaMin || $this->areaMax || $this->hasMortgage || $this->hasBillOfSale
            || $this->notFirstFloor || $this->notTopFloor || $this->onlyTopFloor;
    }

    public function with(): array
    {
        $query = Property::with('category')
            ->where('is_owner', true)
            ->where('bumped_at', '>=', now()->subMonths(3))
            ->orderByDesc('bumped_at');

        if ($this->search) {
            $query->where('location_full_name', 'like', "%{$this->search}%");
        }
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if (!empty($this->locationIds)) {
            $query->whereIn('location_id', $this->locationIds);
        }
        if ($this->rooms) {
            $query->where('rooms', $this->rooms);
        }
        if ($this->priceMin) {
            $query->where('price', '>=', $this->priceMin);
        }
        if ($this->priceMax) {
            $query->where('price', '<=', $this->priceMax);
        }
        if ($this->floorMin) {
            $query->whereRaw('CAST(floor AS UNSIGNED) >= ?', [$this->floorMin]);
        }
        if ($this->floorMax) {
            $query->whereRaw('CAST(floor AS UNSIGNED) <= ?', [$this->floorMax]);
        }
        if ($this->areaMin) {
            $query->where('area', '>=', $this->areaMin);
        }
        if ($this->areaMax) {
            $query->where('area', '<=', $this->areaMax);
        }
        if ($this->hasMortgage) {
            $query->where('has_mortgage', true);
        }
        if ($this->hasBillOfSale) {
            $query->where('has_bill_of_sale', true);
        }
        if ($this->notFirstFloor) {
            $query->whereRaw('CAST(floor AS UNSIGNED) > 1');
        }
        if ($this->notTopFloor) {
            $query->whereRaw('floor_total IS NOT NULL AND CAST(floor AS UNSIGNED) < floor_total');
        }
        if ($this->onlyTopFloor) {
            $query->whereRaw('floor_total IS NOT NULL AND CAST(floor AS UNSIGNED) = floor_total');
        }

        $user = auth()->user();
        $canAccess = $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPlan('platform');

        if (!$canAccess) {
            return ['canAccess' => false, 'properties' => collect(), 'categories' => collect(), 'locations' => collect(), 'totalCount' => 0];
        }

        return [
            'canAccess' => true,
            'properties' => $query->paginate(24),
            'categories' => Category::where('is_active', true)->get(),
            'locations' => Location::where('is_active', true)->orderBy('name_az')->get(),
            'totalCount' => Property::where('is_owner', true)->where('bumped_at', '>=', now()->subMonths(3))->count(),
        ];
    }
}; ?>

<div>
@if(!$canAccess)
    @include('livewire.partials.plan-gate', ['planKey' => 'platform', 'planName' => 'Platforma girişi', 'pageTitle' => 'Elanlar'])
@else
<div class="mx-auto max-w-[1600px]">
    {{-- Two-column layout: main + live feed --}}
    <div class="flex gap-4 items-start" style="min-height: calc(100vh - 6rem);">

    {{-- LEFT: main content --}}
    <div class="flex-1 min-w-0">

    <div class="flex items-center justify-between">
        <flux:heading size="xl">Elanlar</flux:heading>
        <!--<flux:badge>{{ $totalCount }} mülkiyyətçi elanı</flux:badge>-->
    </div>

    {{-- Filtrlər — bir sətirdə kompakt --}}
    <div class="mt-4 flex flex-wrap items-end gap-2">
        <div class="w-40">
            <flux:select wire:model="categoryId" placeholder="Kateqoriya" size="sm">
                <flux:select.option value="">Kateqoriya</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name_az }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Ərazi multi-select --}}
        <div x-data="{
            open: false,
            search: '',
            locations: {{ Js::from($locations->map(fn($l) => ['id' => (string)$l->id, 'name' => $l->name_az])) }},
            get filtered() {
                if (!this.search) return this.locations;
                const s = this.search.toLowerCase();
                return this.locations.filter(l => l.name.toLowerCase().includes(s));
            },
            toggle(id) {
                const ids = [...$wire.locationIds];
                const idx = ids.indexOf(id);
                if (idx > -1) { ids.splice(idx, 1); } else { ids.push(id); }
                $wire.set('locationIds', ids);
            },
            isSelected(id) { return $wire.locationIds.includes(id); }
        }" class="relative w-44" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                class="relative flex h-8 w-full items-center justify-between rounded-md border border-zinc-200 bg-white pl-2.5 pr-7 text-left text-sm shadow-xs outline-none transition hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800"
            >
                <span :class="$wire.locationIds.length ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400'"
                    x-text="$wire.locationIds.length ? $wire.locationIds.length + ' ərazi' : 'Ərazi'" class="truncate text-xs">
                </span>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5">
                    <flux:icon.chevron-up-down class="size-3.5 text-zinc-400" />
                </span>
            </button>

            <div x-show="open" @click.away="open = false" x-transition.opacity
                class="absolute z-50 mt-1 max-h-60 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
            >
                <div class="border-b border-zinc-100 p-1.5 dark:border-zinc-700">
                    <input x-model="search" type="text" placeholder="Axtar..."
                        class="w-full rounded border-0 bg-zinc-50 px-2 py-1 text-xs outline-none placeholder:text-zinc-400 focus:ring-0 dark:bg-zinc-700 dark:text-white"
                        @click.stop
                    />
                </div>
                <div class="max-h-44 overflow-y-auto">
                    <template x-for="loc in filtered" :key="loc.id">
                        <button type="button" @click.stop="toggle(loc.id)"
                            :class="isSelected(loc.id) ? 'bg-zinc-100 dark:bg-zinc-700' : 'hover:bg-zinc-50 dark:hover:bg-zinc-700/50'"
                            class="flex w-full items-center gap-2 px-2.5 py-1 text-left text-xs transition"
                        >
                            <span :class="isSelected(loc.id) ? 'bg-indigo-600 border-indigo-600' : 'border-zinc-300 dark:border-zinc-500'"
                                class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border transition"
                            >
                                <svg x-show="isSelected(loc.id)" class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span x-text="loc.name" class="text-zinc-700 dark:text-zinc-300"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="w-28">
            <flux:select wire:model="rooms" placeholder="Otaq" size="sm">
                <flux:select.option value="">Otaq</flux:select.option>
                @for($i = 1; $i <= 6; $i++)
                    <flux:select.option value="{{ $i }}">{{ $i }} otaq</flux:select.option>
                @endfor
            </flux:select>
        </div>

        <div class="w-28">
            <flux:input wire:model="priceMin" placeholder="Min ₼" type="number" size="sm" />
        </div>
        <div class="w-28">
            <flux:input wire:model="priceMax" placeholder="Max ₼" type="number" size="sm" />
        </div>

        <div class="w-24">
            <flux:input wire:model="floorMin" placeholder="Mərt. min" type="number" size="sm" min="1" />
        </div>
        <div class="w-24">
            <flux:input wire:model="floorMax" placeholder="Mərt. max" type="number" size="sm" min="1" />
        </div>

        <div class="w-24">
            <flux:input wire:model="areaMin" placeholder="m² min" type="number" size="sm" min="1" />
        </div>
        <div class="w-24">
            <flux:input wire:model="areaMax" placeholder="m² maks" type="number" size="sm" min="1" />
        </div>

        <flux:button wire:click="filter" variant="primary" size="sm" icon="funnel">Filtrlə</flux:button>
    </div>

    {{-- Boolean filtrlər --}}
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
        @foreach([
            ['field' => 'hasBillOfSale',  'label' => 'Çıxarış var'],
            ['field' => 'hasMortgage',    'label' => 'İpoteka var'],
            ['field' => 'notFirstFloor',  'label' => '1-ci olmasın'],
            ['field' => 'notTopFloor',    'label' => 'Ən üst olmasın'],
            ['field' => 'onlyTopFloor',   'label' => 'Yalnız ən üst'],
        ] as $opt)
        <label class="flex cursor-pointer items-center gap-1.5 select-none">
            <input type="checkbox"
                wire:model="{{ $opt['field'] }}"
                class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800"
            />
            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $opt['label'] }}</span>
        </label>
        @endforeach
    </div>

    {{-- Aktiv filtrlər badge-ları --}}
    @if($this->hasActiveFilters())
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <span class="text-xs text-zinc-500">Filtrlər:</span>

        @if($categoryId)
            <flux:badge size="sm" color="blue">
                {{ $categories->firstWhere('id', $categoryId)?->name_az }}
                <flux:badge.close wire:click="$set('categoryId', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @foreach($locationIds as $locId)
            @php $loc = $locations->firstWhere('id', $locId); @endphp
            @if($loc)
            <flux:badge size="sm" color="purple">
                {{ $loc->name_az }}
                <flux:badge.close wire:click="removeLocation('{{ $locId }}')" class="cursor-pointer" />
            </flux:badge>
            @endif
        @endforeach

        @if($rooms)
            <flux:badge size="sm" color="green">
                {{ $rooms }} otaq
                <flux:badge.close wire:click="$set('rooms', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($priceMin || $priceMax)
            <flux:badge size="sm" color="amber">
                {{ $priceMin ?: '0' }} - {{ $priceMax ?: '∞' }} AZN
                <flux:badge.close wire:click="$set('priceMin', ''); $set('priceMax', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->floorMin || $this->floorMax)
            <flux:badge size="sm" color="cyan">
                Mərtəbə: {{ $this->floorMin ?: '1' }} - {{ $this->floorMax ?: '∞' }}
                <flux:badge.close wire:click="$set('floorMin', ''); $set('floorMax', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->areaMin || $this->areaMax)
            <flux:badge size="sm" color="lime">
                {{ $this->areaMin ?: '0' }} - {{ $this->areaMax ?: '∞' }} m²
                <flux:badge.close wire:click="$set('areaMin', ''); $set('areaMax', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->hasBillOfSale)
            <flux:badge size="sm" color="green">Çıxarış var
                <flux:badge.close wire:click="$set('hasBillOfSale', false)" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->hasMortgage)
            <flux:badge size="sm" color="blue">İpoteka var
                <flux:badge.close wire:click="$set('hasMortgage', false)" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->notFirstFloor)
            <flux:badge size="sm" color="zinc">1-ci olmasın
                <flux:badge.close wire:click="$set('notFirstFloor', false)" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->notTopFloor)
            <flux:badge size="sm" color="zinc">Ən üst olmasın
                <flux:badge.close wire:click="$set('notTopFloor', false)" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->onlyTopFloor)
            <flux:badge size="sm" color="amber">Yalnız ən üst
                <flux:badge.close wire:click="$set('onlyTopFloor', false)" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($search)
            <flux:badge size="sm" color="zinc">
                "{{ $search }}"
                <flux:badge.close wire:click="$set('search', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        <button wire:click="clearFilters" class="text-xs text-red-500 hover:underline">Filtri sıfırla</button>
    </div>
    @endif

    {{-- Card grid --}}
    <div class="relative"
         wire:loading.class="opacity-30 pointer-events-none"
         style="transition: opacity 0.25s ease;">

        {{-- Centered loader --}}
        <div wire:loading class="absolute inset-0 z-10 flex items-center justify-center">
            <flux:icon.arrow-path class="size-8 animate-spin text-indigo-500" />
        </div>

    @if($properties->isEmpty())
        <div class="mt-12 py-16 text-center text-zinc-400">
            Filtrə uyğun elan tapılmadı.
        </div>
    @else
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($properties as $property)
        @php
            $thumb = null;
            if (!empty($property->photos)) {
                $thumb = $property->photos[0]['medium'] ?? $property->photos[0]['thumb'] ?? $property->photos[0]['large'] ?? null;
            }
            $delay = ($loop->index % 12) * 40;
        @endphp
        <a href="{{ $property->full_url }}" target="_blank"
           style="animation: fadeSlideUp 0.4s ease both; animation-delay: {{ $delay }}ms;"
           class="property-card group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md hover:-translate-y-0.5 dark:border-zinc-700 dark:bg-zinc-800">
            {{-- Photo --}}
            <div class="relative aspect-[4/3] overflow-hidden bg-zinc-200 dark:bg-zinc-700">
                @if($thumb)
                    <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                @else
                    <div class="flex h-full w-full items-center justify-center text-zinc-400">
                        <flux:icon.photo class="size-10 opacity-40" />
                    </div>
                @endif


                {{-- Category badge --}}
                @if($property->category)
                <div class="absolute top-2 left-2 rounded-md bg-black/50 px-1.5 py-0.5 text-xs text-white">
                    {{ $property->category->name_az }}
                </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex flex-1 flex-col gap-1 p-3">
                {{-- Price --}}
                <div class="text-base font-bold text-zinc-900 dark:text-white">
                    @if($property->price)
                        {{ number_format($property->price) }} {{ $property->currency === 'azn' ? '₼' : ($property->currency === 'usd' ? '$' : $property->currency) }}
                    @else
                        <span class="text-zinc-400 font-normal text-sm">Qiymət yox</span>
                    @endif
                </div>

                {{-- Specs: rooms • area • floor --}}
                <div class="flex flex-wrap items-center gap-x-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                    @if($property->rooms)
                        <span>{{ $property->rooms }} otaqlı</span>
                    @endif
                    @if($property->rooms && $property->area)
                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                    @endif
                    @if($property->area)
                        <span>{{ $property->area }} m²</span>
                    @endif
                    @if(($property->rooms || $property->area) && $property->floor)
                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                    @endif
                    @if($property->floor)
                        <span>{{ $property->floor }}{{ $property->floor_total ? '/'.$property->floor_total : '' }} mərtəbə</span>
                    @endif
                </div>

                {{-- Location --}}
                @if($property->location_full_name)
                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $property->location_full_name }}
                </div>
                @endif

                {{-- Date --}}
                <div class="mt-auto pt-1 text-xs text-zinc-400">
                    {{ $property->bumped_at?->format('d.m.Y H:i') }}
                </div>
            </div>
        </a>
        @endforeach
    </div>
    @endif

    </div>{{-- end results wrapper --}}

    <div class="mt-6">
        {{ $properties->links() }}
    </div>

    </div>{{-- end LEFT --}}

    {{-- RIGHT: live feed panel --}}
    <div class="w-72 shrink-0 sticky top-4 self-start rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
         style="background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%); height: calc(100vh - 3rem);">
        @livewire('properties.live-feed')
    </div>

    </div>{{-- end two-column --}}
</div>
</div>
@endif

<style>
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes progressSlide {
    0%   { transform: translateX(-100%); }
    50%  { transform: translateX(150%); }
    100% { transform: translateX(400%); }
}
.progress-bar {
    animation: progressSlide 1.2s ease-in-out infinite;
}
</style>

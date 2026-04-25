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
    #[Url] public string $roomMin = '';
    #[Url] public string $roomMax = '';
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
        $this->reset(['search', 'categoryId', 'locationIds', 'roomMin', 'roomMax', 'priceMin', 'priceMax',
            'floorMin', 'floorMax', 'areaMin', 'areaMax', 'hasMortgage', 'hasBillOfSale',
            'notFirstFloor', 'notTopFloor', 'onlyTopFloor']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->search || $this->categoryId || !empty($this->locationIds) || $this->roomMin || $this->roomMax
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
            $level1 = \App\Models\Location::whereIn('parent_id', $this->locationIds)->pluck('id')->toArray();
            $level2 = $level1 ? \App\Models\Location::whereIn('parent_id', $level1)->pluck('id')->toArray() : [];
            $query->whereIn('location_id', array_merge($this->locationIds, $level1, $level2));
        }
        if ($this->roomMin) {
            $query->where('rooms', '>=', $this->roomMin);
        }
        if ($this->roomMax) {
            $query->where('rooms', '<=', $this->roomMax);
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
        $canAccess = $user->hasAnyRole(['superadmin', 'admin']) || $user->hasFeature('properties_view');

        if (!$canAccess) {
            return ['canAccess' => false, 'properties' => collect(), 'categories' => collect(), 'locations' => collect(), 'totalCount' => 0];
        }

        $properties = $query->paginate(24);

        $savedPropertyIds = \App\Models\SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
            ->whereIn('property_id', $properties->pluck('id'))
            ->pluck('property_id')
            ->flip()
            ->toArray();

        return [
            'canAccess' => true,
            'properties' => $properties,
            'savedPropertyIds' => $savedPropertyIds,
            'categories' => cache()->remember('properties:categories', 300, fn() => Category::where('is_active', true)->get()),
            'locations' => cache()->remember('properties:locations', 300, fn() => Location::where('is_active', true)->orderBy('name_az')->get()),
            'totalCount' => cache()->remember('properties:total_count', 60, fn() => Property::where('is_owner', true)->where('bumped_at', '>=', now()->subMonths(3))->count()),
        ];
    }
}; ?>

<div>
@if(!$canAccess)
    @include('livewire.partials.plan-gate', ['planKey' => 'platform', 'planName' => 'Giriş tarifi', 'pageTitle' => 'Elanlar'])
@else
<div class="mx-auto max-w-[1600px]">
    {{-- Two-column layout: main + live feed --}}
    <div class="flex gap-4 items-start">

    {{-- LEFT: main content --}}
    <div class="flex-1 min-w-0" style="margin-right: 18rem;">

    <div class="flex items-center justify-between">
        <flux:heading size="xl">Elanlar</flux:heading>
    </div>

    {{-- Filtrlər --}}
    <div class="mt-4">
        @include('livewire.partials.filter-fields', [
            'categories' => $categories,
            'locations'  => $locations,
            'f' => [
                'category'      => 'categoryId',
                'locationIds'   => 'locationIds',
                'roomMin'       => 'roomMin',
                'roomMax'       => 'roomMax',
                'priceMin'      => 'priceMin',
                'priceMax'      => 'priceMax',
                'floorMin'      => 'floorMin',
                'floorMax'      => 'floorMax',
                'areaMin'       => 'areaMin',
                'areaMax'       => 'areaMax',
                'hasMortgage'   => 'hasMortgage',
                'hasBillOfSale' => 'hasBillOfSale',
                'notFirstFloor' => 'notFirstFloor',
                'notTopFloor'   => 'notTopFloor',
                'onlyTopFloor'  => 'onlyTopFloor',
            ],
        ])
        <div class="mt-2">
            <flux:button wire:click="filter" variant="primary" size="sm" icon="funnel">Filtrlə</flux:button>
        </div>
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

        @if($this->roomMin || $this->roomMax)
            <flux:badge size="sm" color="green">
                {{ $this->roomMin ?: '1' }}-{{ $this->roomMax ?: '∞' }} otaq
                <flux:badge.close wire:click="$set('roomMin', ''); $set('roomMax', '')" class="cursor-pointer" />
            </flux:badge>
        @endif

        @if($this->priceMin || $this->priceMax)
            <flux:badge size="sm" color="amber">
                {{ $this->priceMin ?: '0' }} - {{ $this->priceMax ?: '∞' }} AZN
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
        <div style="animation: fadeSlideUp 0.4s ease both; animation-delay: {{ $delay }}ms;"
             class="property-card group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md hover:-translate-y-0.5 dark:border-zinc-700 dark:bg-zinc-800">
            {{-- Photo --}}
            <a href="{{ $property->full_url }}" target="_blank" class="relative block aspect-[4/3] overflow-hidden bg-zinc-200 dark:bg-zinc-700">
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

                {{-- Info button --}}
                <button
                    onclick="event.preventDefault(); event.stopPropagation(); window.location='{{ route('properties.show', $property->id) }}'"
                    class="absolute bottom-2 right-10 flex items-center justify-center size-7 rounded-full bg-black/50 hover:bg-white/20 text-white backdrop-blur-sm transition-all"
                    title="Ətraflı bax">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                    </svg>
                </button>
                {{-- Save button --}}
                @php $isSaved = isset($savedPropertyIds[$property->id]); @endphp
                <button
                    x-data="{ saved: {{ $isSaved ? 'true' : 'false' }} }"
                    x-on:bookmark-changed.window="if ($event.detail.propertyId == {{ $property->id }}) saved = $event.detail.isSaved"
                    onclick="event.preventDefault(); event.stopPropagation(); Livewire.dispatch('save-property', { propertyId: {{ $property->id }} })"
                    :class="saved ? 'bg-indigo-500 hover:bg-indigo-600' : 'bg-black/50 hover:bg-indigo-500'"
                    class="absolute bottom-2 right-2 flex items-center justify-center size-7 rounded-full text-white backdrop-blur-sm transition-all"
                    title="Siyahıya əlavə et">
                    <svg class="size-4" :fill="saved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                    </svg>
                </button>
            </a>

            {{-- Info --}}
            <a href="{{ $property->full_url }}" target="_blank" class="flex flex-1 flex-col gap-1 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
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
            </a>
        </div>
        @endforeach
    </div>
    @endif

    </div>{{-- end results wrapper --}}

    <div class="mt-6">
        {{ $properties->links() }}
    </div>

    </div>{{-- end LEFT --}}

    {{-- RIGHT: live feed panel --}}
    <div class="w-72 shrink-0 rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
         style="background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);
                position: fixed; top: 1rem; right: 1rem; bottom: 1rem;">
        <x-live-feed />
    </div>

    </div>{{-- end two-column --}}
</div>{{-- end max-[1600px] --}}

    @livewire('properties.save-modal', key('save-modal'))

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
</div>

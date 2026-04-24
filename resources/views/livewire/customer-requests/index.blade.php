<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Location;
use App\Models\PropertyMatch;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $activeTab = 'requests';
    public string $matchStatus = 'new';
    public string $filterRequestId = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $customer_id = '';
    public string $name = '';
    public string $category_id = '';
    public array $location_ids = [];
    public string $price_min = '';
    public string $price_max = '';
    public string $room_min = '';
    public string $room_max = '';
    public string $area_min = '';
    public string $area_max = '';
    public bool $is_active = true;

    public function updatedActiveTab(): void { $this->resetPage(); }
    public function updatedMatchStatus(): void { $this->resetPage(); }
    public function updatedFilterRequestId(): void { $this->resetPage(); }

    public function switchTab(string $tab, ?int $requestId = null): void
    {
        $this->activeTab = $tab;
        $this->filterRequestId = $requestId ? (string)$requestId : '';
        $this->matchStatus = 'new';
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'customer_id', 'name', 'category_id', 'location_ids', 'price_min', 'price_max', 'room_min', 'room_max', 'area_min', 'area_max']);
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $request = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $this->editingId = $request->id;
        $this->customer_id = (string) $request->customer_id;
        $this->name = $request->name;
        $this->is_active = $request->is_active;

        $filters = $request->filters;
        $this->category_id = $filters['categoryId'] ?? '';
        $this->location_ids = $filters['locationIds'] ?? [];
        $this->price_min = $filters['priceMin'] ?? '';
        $this->price_max = $filters['priceMax'] ?? '';
        $this->room_min = $filters['roomMin'] ?? '';
        $this->room_max = $filters['roomMax'] ?? '';
        $this->area_min = $filters['areaMin'] ?? '';
        $this->area_max = $filters['areaMax'] ?? '';

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'name' => 'required|min:2|max:255',
        ]);

        $filters = array_filter([
            'categoryId' => $this->category_id ?: null,
            'locationIds' => !empty($this->location_ids) ? $this->location_ids : null,
            'priceMin' => $this->price_min ? (int) $this->price_min : null,
            'priceMax' => $this->price_max ? (int) $this->price_max : null,
            'roomMin' => $this->room_min ? (int) $this->room_min : null,
            'roomMax' => $this->room_max ? (int) $this->room_max : null,
            'areaMin' => $this->area_min ? (int) $this->area_min : null,
            'areaMax' => $this->area_max ? (int) $this->area_max : null,
        ], fn($v) => $v !== null);

        CustomerRequest::updateOrCreate(
            ['id' => $this->editingId],
            [
                'customer_id' => $this->customer_id,
                'user_id' => auth()->id(),
                'name' => $this->name,
                'filters' => $filters,
                'is_active' => $this->is_active,
            ]
        );

        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $request = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $request->update(['is_active' => !$request->is_active]);
    }

    public function delete(int $id): void
    {
        CustomerRequest::where('user_id', auth()->id())->findOrFail($id)->delete();
    }

    public function markViewed(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)
            ->update(['status' => 'viewed']);
    }

    public function dismiss(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)
            ->update(['status' => 'dismissed']);
    }

    public function with(): array
    {
        $user = auth()->user();
        $canAccess = $user->hasAnyRole(['superadmin', 'admin']) || $user->hasFeature('requests');
        $canAccessRequests = $canAccess;
        $canAccessMatches  = $canAccess;

        $requests = collect();
        $matches = collect();
        $matchCounts = ['new' => 0, 'viewed' => 0, 'dismissed' => 0];

        if ($canAccessRequests) {
            $requests = CustomerRequest::with('customer')
                ->where('user_id', auth()->id())
                ->withCount('matches')
                ->withCount(['matches as new_matches_count' => fn($q) => $q->where('status', 'new')])
                ->orderByDesc('created_at')
                ->paginate(15);
        }

        if ($canAccessMatches) {
            $matchQuery = PropertyMatch::with(['property', 'customerRequest.customer'])
                ->where('user_id', auth()->id())
                ->where('status', $this->matchStatus)
                ->orderByDesc('created_at');

            if ($this->filterRequestId) {
                $matchQuery->where('customer_request_id', $this->filterRequestId);
            }

            $matches = $matchQuery->paginate(20);

            $countBase = PropertyMatch::where('user_id', auth()->id());
            if ($this->filterRequestId) {
                $countBase->where('customer_request_id', $this->filterRequestId);
            }
            $matchCounts = [
                'new' => (clone $countBase)->where('status', 'new')->count(),
                'viewed' => (clone $countBase)->where('status', 'viewed')->count(),
                'dismissed' => (clone $countBase)->where('status', 'dismissed')->count(),
            ];
        }

        return [
            'canAccessRequests' => $canAccessRequests,
            'canAccessMatches'  => $canAccessMatches,
            'requests' => $requests,
            'matches'  => $matches,
            'matchCounts' => $matchCounts,
            'customers' => Customer::where('user_id', auth()->id())->get(),
            'categories' => Category::where('is_active', true)->get(),
            'locations' => Location::where('is_active', true)->orderBy('name_az')->get(),
            'allRequests' => CustomerRequest::where('user_id', auth()->id())->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between">
        <flux:heading size="xl">İstəklər və Uyğunluqlar</flux:heading>
        @if($activeTab === 'requests' && $canAccessRequests)
            <flux:button wire:click="create" variant="primary" icon="plus" size="sm">Yeni istək</flux:button>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mt-4 flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="switchTab('requests')"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                {{ $activeTab === 'requests'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <span class="flex items-center gap-2">
                <flux:icon.clipboard-document-list class="size-4" />
                İstəklər
            </span>
        </button>
        <button
            wire:click="switchTab('matches')"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                {{ $activeTab === 'matches'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <span class="flex items-center gap-2">
                <flux:icon.check-badge class="size-4" />
                Uyğunluqlar
                @if($matchCounts['new'] > 0)
                    <span class="inline-flex items-center justify-center size-5 rounded-full bg-indigo-600 text-white text-xs font-bold">{{ $matchCounts['new'] }}</span>
                @endif
            </span>
        </button>
    </div>

    {{-- İSTƏKLƏR TAB --}}
    @if($activeTab === 'requests')
        @if(!$canAccessRequests)
            @include('livewire.partials.plan-gate', ['planKey' => 'requests', 'planName' => 'İstəklər və Uyğunluqlar', 'pageTitle' => 'İstəklər'])
        @else
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Ad</flux:table.column>
                <flux:table.column>Müştəri</flux:table.column>
                <flux:table.column>Filtrlər</flux:table.column>
                <flux:table.column>Uyğunluq</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($requests as $request)
                <flux:table.row>
                    <flux:table.cell class="font-medium">{{ $request->name }}</flux:table.cell>
                    <flux:table.cell>{{ $request->customer?->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @if(!empty($request->filters['categoryId']))
                                <flux:badge size="sm" color="blue">{{ $categories->firstWhere('bina_id', $request->filters['categoryId'])?->name_az ?? $request->filters['categoryId'] }}</flux:badge>
                            @endif
                            @if(!empty($request->filters['priceMin']) || !empty($request->filters['priceMax']))
                                <flux:badge size="sm" color="green">{{ $request->filters['priceMin'] ?? '0' }}-{{ $request->filters['priceMax'] ?? '∞' }} AZN</flux:badge>
                            @endif
                            @if(!empty($request->filters['roomMin']) || !empty($request->filters['roomMax']))
                                <flux:badge size="sm" color="purple">{{ $request->filters['roomMin'] ?? '1' }}-{{ $request->filters['roomMax'] ?? '∞' }} otaq</flux:badge>
                            @endif
                            @if(!empty($request->filters['locationIds']))
                                <flux:badge size="sm" color="amber">{{ count($request->filters['locationIds']) }} ərazi</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <button wire:click="switchTab('matches', {{ $request->id }})" class="flex items-center gap-1 hover:opacity-70 transition-opacity">
                            <flux:badge size="sm" color="{{ $request->new_matches_count > 0 ? 'green' : 'zinc' }}">
                                {{ $request->matches_count }}
                            </flux:badge>
                            @if($request->new_matches_count > 0)
                                <span class="text-xs text-green-600 font-medium">{{ $request->new_matches_count }} yeni</span>
                            @endif
                        </button>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:click="toggleActive({{ $request->id }})" size="xs" variant="ghost">
                            @if($request->is_active)
                                <flux:badge color="green" size="sm">Aktiv</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Dayandırılıb</flux:badge>
                            @endif
                        </flux:button>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button wire:click="edit({{ $request->id }})" size="xs" variant="ghost" icon="pencil-square" />
                            <flux:button wire:click="delete({{ $request->id }})" wire:confirm="Bu istəyi silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    @endif

    {{-- UYĞUNLUQLAR TAB --}}
    @if($activeTab === 'matches')
        @if(!$canAccessMatches)
            @include('livewire.partials.plan-gate', ['planKey' => 'requests', 'planName' => 'İstəklər və Uyğunluqlar', 'pageTitle' => 'Uyğunluqlar'])
        @else
        <div class="mt-4 flex flex-wrap items-center gap-3">
            {{-- Request filter --}}
            <div class="flex-1 min-w-48 max-w-xs">
                <flux:select wire:model.live="filterRequestId" size="sm">
                    <flux:select.option value="">Bütün istəklər</flux:select.option>
                    @foreach($allRequests as $req)
                        <flux:select.option value="{{ $req->id }}">{{ $req->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Status tabs --}}
            <div class="flex gap-1">
                <flux:button wire:click="$set('matchStatus', 'new')" variant="{{ $matchStatus === 'new' ? 'primary' : 'ghost' }}" size="sm">
                    Yeni ({{ $matchCounts['new'] }})
                </flux:button>
                <flux:button wire:click="$set('matchStatus', 'viewed')" variant="{{ $matchStatus === 'viewed' ? 'primary' : 'ghost' }}" size="sm">
                    Baxılıb ({{ $matchCounts['viewed'] }})
                </flux:button>
                <flux:button wire:click="$set('matchStatus', 'dismissed')" variant="{{ $matchStatus === 'dismissed' ? 'primary' : 'ghost' }}" size="sm">
                    Keçildi ({{ $matchCounts['dismissed'] }})
                </flux:button>
            </div>

            @if($filterRequestId)
                <flux:button wire:click="$set('filterRequestId', '')" size="sm" variant="ghost" icon="x-mark">
                    Filtri sıfırla
                </flux:button>
            @endif
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Şəkil</flux:table.column>
                <flux:table.column>Müştəri / İstək</flux:table.column>
                <flux:table.column>Qiymət</flux:table.column>
                <flux:table.column>Otaq</flux:table.column>
                <flux:table.column>Sahə</flux:table.column>
                <flux:table.column>Ərazi</flux:table.column>
                <flux:table.column>Tapıldı</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($matches as $match)
                <flux:table.row>
                    <flux:table.cell>
                        @if($match->property?->photos && count($match->property->photos) > 0)
                            <img src="{{ $match->property->photos[0] }}" alt="" class="h-12 w-16 rounded object-cover" loading="lazy">
                        @else
                            <div class="flex h-12 w-16 items-center justify-center rounded bg-zinc-200 text-xs text-zinc-400">Yox</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $match->customerRequest?->customer?->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $match->customerRequest?->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="font-semibold">{{ number_format($match->property?->price) }} {{ $match->property?->currency }}</flux:table.cell>
                    <flux:table.cell>{{ $match->property?->rooms }}</flux:table.cell>
                    <flux:table.cell>{{ $match->property?->area }} m²</flux:table.cell>
                    <flux:table.cell class="max-w-40 truncate">{{ $match->property?->location_full_name }}</flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $match->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <a href="{{ $match->property?->full_url }}" target="_blank">
                                <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" />
                            </a>
                            @if($match->status === 'new')
                                <flux:button wire:click="markViewed({{ $match->id }})" size="xs" variant="ghost" icon="eye" />
                            @endif
                            @if($match->status !== 'dismissed')
                                <flux:button wire:click="dismiss({{ $match->id }})" size="xs" variant="ghost" icon="x-mark" class="text-red-500" />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
                @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                        Bu statusda uyğunluq yoxdur.
                    </flux:table.cell>
                </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">{{ $matches->links() }}</div>
        @endif
    @endif

    {{-- Form Modal --}}
    <flux:modal wire:model="showForm" class="max-w-2xl">
        <flux:heading>{{ $editingId ? 'İstəyi redaktə' : 'Yeni istək' }}</flux:heading>
        <form wire:submit="save" class="mt-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="customer_id" label="Müştəri" required>
                    <flux:select.option value="">Seçin...</flux:select.option>
                    @foreach($customers as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" label="İstək adı" placeholder="Məs: Yasamal 2 otaq" required />
            </div>

            <flux:separator text="Filtrlər" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="category_id" label="Kateqoriya">
                    <flux:select.option value="">Hamısı</flux:select.option>
                    @foreach($categories as $cat)
                        <flux:select.option value="{{ $cat->bina_id }}">{{ $cat->name_az }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex items-end pb-1">
                    <flux:checkbox wire:model="is_active" label="Aktiv" />
                </div>
            </div>

            <div>
                <flux:heading size="sm" class="mb-1">Ərazilər</flux:heading>
                @if(!empty($location_ids))
                <div class="mb-1 flex flex-wrap gap-1">
                    @foreach($location_ids as $locBinaId)
                        @php($loc = $locations->firstWhere('bina_id', $locBinaId))
                        @if($loc)
                        <flux:badge size="sm">
                            {{ $loc->name_az }}
                            <flux:badge.close wire:click="$set('location_ids', {{ json_encode(array_values(array_diff($location_ids, [$locBinaId]))) }})" class="cursor-pointer" />
                        </flux:badge>
                        @endif
                    @endforeach
                </div>
                @endif
                <flux:select wire:model.live="location_ids" multiple searchable placeholder="Ərazi seçin...">
                    @foreach($locations as $loc)
                        <flux:select.option value="{{ $loc->bina_id }}">{{ $loc->name_az }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="price_min" label="Min qiymət (AZN)" type="number" />
                <flux:input wire:model="price_max" label="Max qiymət (AZN)" type="number" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="room_min" label="Min otaq" type="number" min="1" max="10" />
                <flux:input wire:model="room_max" label="Max otaq" type="number" min="1" max="10" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="area_min" label="Min sahə (m²)" type="number" />
                <flux:input wire:model="area_max" label="Max sahə (m²)" type="number" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showForm', false)" variant="ghost">Ləğv et</flux:button>
                <flux:button type="submit" variant="primary">Saxla</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

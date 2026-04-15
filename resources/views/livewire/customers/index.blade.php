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

    // Left panel
    public string $search = '';
    public ?int $selectedCustomerId = null;

    // Right panel
    public string $rightTab = 'requests';
    public string $matchStatus = 'new';

    // Customer form
    public bool $showCustomerForm = false;
    public ?int $editingCustomerId = null;
    public string $cName = '';
    public string $cPhone = '';
    public string $cWhatsapp = '';
    public string $cNotes = '';

    // Request form
    public bool $showRequestForm = false;
    public ?int $editingRequestId = null;
    public string $rName = '';
    public string $rCategoryId = '';
    public array $rLocationIds = [];
    public string $rPriceMin = '';
    public string $rPriceMax = '';
    public string $rRoomMin = '';
    public string $rRoomMax = '';
    public string $rAreaMin = '';
    public string $rAreaMax = '';
    public bool $rIsActive = true;

    public function updatedSearch(): void { $this->resetPage(); $this->selectedCustomerId = null; }
    public function updatedRightTab(): void { $this->matchStatus = 'new'; }

    // ── Customer actions ──────────────────────────────────────────

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $this->selectedCustomerId === $id ? null : $id;
        $this->rightTab = 'requests';
        $this->matchStatus = 'new';
    }

    public function createCustomer(): void
    {
        $this->reset(['editingCustomerId', 'cName', 'cPhone', 'cWhatsapp', 'cNotes']);
        $this->showCustomerForm = true;
    }

    public function editCustomer(int $id): void
    {
        $c = Customer::where('user_id', auth()->id())->findOrFail($id);
        $this->editingCustomerId = $c->id;
        $this->cName     = $c->name;
        $this->cPhone    = $c->phone ?? '';
        $this->cWhatsapp = $c->whatsapp ?? '';
        $this->cNotes    = $c->notes ?? '';
        $this->showCustomerForm = true;
    }

    public function saveCustomer(): void
    {
        $this->validate([
            'cName'     => 'required|min:2|max:255',
            'cPhone'    => 'nullable|max:20',
            'cWhatsapp' => 'nullable|max:20',
        ]);

        $customer = Customer::updateOrCreate(
            ['id' => $this->editingCustomerId],
            [
                'user_id'  => auth()->id(),
                'name'     => $this->cName,
                'phone'    => $this->cPhone ?: null,
                'whatsapp' => $this->cWhatsapp ?: null,
                'notes'    => $this->cNotes ?: null,
            ]
        );

        $this->selectedCustomerId = $customer->id;
        $this->showCustomerForm = false;
        $this->reset(['editingCustomerId', 'cName', 'cPhone', 'cWhatsapp', 'cNotes']);
    }

    public function deleteCustomer(int $id): void
    {
        Customer::where('user_id', auth()->id())->findOrFail($id)->delete();
        if ($this->selectedCustomerId === $id) {
            $this->selectedCustomerId = null;
        }
    }

    // ── Request actions ───────────────────────────────────────────

    public function createRequest(): void
    {
        $this->reset(['editingRequestId', 'rName', 'rCategoryId', 'rLocationIds', 'rPriceMin', 'rPriceMax', 'rRoomMin', 'rRoomMax', 'rAreaMin', 'rAreaMax']);
        $this->rIsActive = true;
        $this->showRequestForm = true;
    }

    public function editRequest(int $id): void
    {
        $req = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $this->editingRequestId = $req->id;
        $this->rName       = $req->name;
        $this->rIsActive   = $req->is_active;
        $f = $req->filters;
        $this->rCategoryId  = $f['categoryId'] ?? '';
        $this->rLocationIds = $f['locationIds'] ?? [];
        $this->rPriceMin    = $f['priceMin'] ?? '';
        $this->rPriceMax    = $f['priceMax'] ?? '';
        $this->rRoomMin     = $f['roomMin'] ?? '';
        $this->rRoomMax     = $f['roomMax'] ?? '';
        $this->rAreaMin     = $f['areaMin'] ?? '';
        $this->rAreaMax     = $f['areaMax'] ?? '';
        $this->showRequestForm = true;
    }

    public function saveRequest(): void
    {
        $this->validate(['rName' => 'required|min:2|max:255']);

        $filters = array_filter([
            'categoryId'  => $this->rCategoryId ?: null,
            'locationIds' => !empty($this->rLocationIds) ? $this->rLocationIds : null,
            'priceMin'    => $this->rPriceMin ? (int) $this->rPriceMin : null,
            'priceMax'    => $this->rPriceMax ? (int) $this->rPriceMax : null,
            'roomMin'     => $this->rRoomMin ? (int) $this->rRoomMin : null,
            'roomMax'     => $this->rRoomMax ? (int) $this->rRoomMax : null,
            'areaMin'     => $this->rAreaMin ? (int) $this->rAreaMin : null,
            'areaMax'     => $this->rAreaMax ? (int) $this->rAreaMax : null,
        ], fn($v) => $v !== null);

        CustomerRequest::updateOrCreate(
            ['id' => $this->editingRequestId],
            [
                'customer_id' => $this->selectedCustomerId,
                'user_id'     => auth()->id(),
                'name'        => $this->rName,
                'filters'     => $filters,
                'is_active'   => $this->rIsActive,
            ]
        );

        $this->showRequestForm = false;
    }

    public function toggleRequestActive(int $id): void
    {
        $req = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $req->update(['is_active' => !$req->is_active]);
    }

    public function deleteRequest(int $id): void
    {
        CustomerRequest::where('user_id', auth()->id())->findOrFail($id)->delete();
    }

    // ── Match actions ─────────────────────────────────────────────

    public function markViewed(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update(['status' => 'viewed']);
    }

    public function dismiss(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update(['status' => 'dismissed']);
    }

    // ── Data ──────────────────────────────────────────────────────

    public function with(): array
    {
        $user = auth()->user();
        $childIds = $user->childrenIds();
        $visibleUserIds = array_merge([$user->id], $childIds);
        $hasChildren = count($childIds) > 0;
        $canUseRequests = $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPlan('requests');

        // Left panel: customer list
        $customerQuery = Customer::whereIn('user_id', $visibleUserIds)
            ->withCount('requests')
            ->withCount(['requests as new_matches_count' => function ($q) {
                $q->whereHas('matches', fn($m) => $m->where('status', 'new'));
            }])
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($this->search) {
            $customerQuery->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $customers = $customerQuery->get();

        // Right panel data
        $selectedCustomer = null;
        $requests = collect();
        $matches = collect();
        $matchCounts = ['new' => 0, 'viewed' => 0, 'dismissed' => 0];

        if ($this->selectedCustomerId) {
            $selectedCustomer = Customer::whereIn('user_id', $visibleUserIds)
                ->with('user:id,name')
                ->find($this->selectedCustomerId);

            if ($selectedCustomer && $canUseRequests) {
                $requests = CustomerRequest::where('customer_id', $this->selectedCustomerId)
                    ->where('user_id', auth()->id())
                    ->withCount('matches')
                    ->withCount(['matches as new_matches_count' => fn($q) => $q->where('status', 'new')])
                    ->orderByDesc('created_at')
                    ->get();

                $requestIds = $requests->pluck('id');

                if ($this->rightTab === 'matches' && $requestIds->isNotEmpty()) {
                    $matches = PropertyMatch::with(['property', 'customerRequest'])
                        ->where('user_id', auth()->id())
                        ->whereIn('customer_request_id', $requestIds)
                        ->where('status', $this->matchStatus)
                        ->orderByDesc('created_at')
                        ->paginate(20);

                    $matchCounts = [
                        'new'       => PropertyMatch::where('user_id', auth()->id())->whereIn('customer_request_id', $requestIds)->where('status', 'new')->count(),
                        'viewed'    => PropertyMatch::where('user_id', auth()->id())->whereIn('customer_request_id', $requestIds)->where('status', 'viewed')->count(),
                        'dismissed' => PropertyMatch::where('user_id', auth()->id())->whereIn('customer_request_id', $requestIds)->where('status', 'dismissed')->count(),
                    ];
                }
            }
        }

        return [
            'customers'        => $customers,
            'hasChildren'      => $hasChildren,
            'selectedCustomer' => $selectedCustomer,
            'canUseRequests'   => $canUseRequests,
            'requests'         => $requests,
            'matches'          => $matches,
            'matchCounts'      => $matchCounts,
            'categories'       => Category::where('is_active', true)->get(),
            'locations'        => Location::where('is_active', true)->orderBy('name_az')->get(),
        ];
    }
}; ?>

<div>
<div class="flex gap-0 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-md" style="height: calc(100vh - 8rem)">

    {{-- ════ SOL PANEL: MÜŞTƏRİLƏR ════ --}}
    <div class="flex flex-col border-r border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 h-full"
         style="width: 300px; min-width: 300px;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-3 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <span class="font-semibold text-sm text-zinc-700 dark:text-zinc-200">Müştərilər</span>
            <flux:button wire:click="createCustomer" variant="primary" icon="plus" size="xs">Yeni</flux:button>
        </div>

        {{-- Search --}}
        <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Axtar..." icon="magnifying-glass" size="sm" />
        </div>

        {{-- Customer list --}}
        <div class="flex-1 overflow-y-auto">
            @forelse($customers as $customer)
            @php $isSelected = $selectedCustomerId === $customer->id; @endphp
            <button
                wire:click="selectCustomer({{ $customer->id }})"
                class="w-full text-left px-3 py-3 border-b border-zinc-100 dark:border-zinc-800 transition-colors
                    {{ $isSelected
                        ? 'bg-indigo-50 dark:bg-indigo-950/40 border-l-2 border-l-indigo-500'
                        : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50 border-l-2 border-l-transparent' }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-sm truncate {{ $isSelected ? 'text-indigo-700 dark:text-indigo-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                            {{ $customer->name }}
                        </div>
                        @if($customer->phone)
                        <div class="text-xs text-zinc-500 mt-0.5">{{ $customer->phone }}</div>
                        @endif
                        @if($hasChildren && $customer->user_id !== auth()->id())
                        <div class="mt-1">
                            <flux:badge size="sm" color="blue">{{ $customer->user->name }}</flux:badge>
                        </div>
                        @endif
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span wire:loading wire:target="selectCustomer({{ $customer->id }})"
                              class="inline-flex items-center justify-center size-5">
                            <flux:icon.arrow-path class="size-4 animate-spin text-indigo-400" />
                        </span>
                        <span wire:loading.remove wire:target="selectCustomer({{ $customer->id }})" class="flex flex-col items-end gap-1">
                            @if($customer->new_matches_count > 0)
                                <span class="inline-flex items-center justify-center size-5 rounded-full bg-green-500 text-white text-xs font-bold">
                                    {{ $customer->new_matches_count }}
                                </span>
                            @endif
                            @if($customer->requests_count > 0)
                                <span class="text-xs text-zinc-400">{{ $customer->requests_count }} istək</span>
                            @endif
                        </span>
                    </div>
                </div>
            </button>
            @empty
            <div class="px-4 py-8 text-center text-sm text-zinc-400">
                Müştəri tapılmadı
            </div>
            @endforelse
        </div>
    </div>

    {{-- ════ SAĞ PANEL: DETAL ════ --}}
    <div class="flex-1 flex flex-col min-w-0 bg-zinc-50 dark:bg-zinc-950 h-full overflow-hidden">


        <div wire:loading.remove wire:target="selectCustomer" class="flex flex-col h-full overflow-hidden">
        @if(!$selectedCustomer)
        {{-- Empty state --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-zinc-400">
            <flux:icon.user-group class="size-12 opacity-30" />
            <p class="text-sm">Müştəri seçin</p>
        </div>

        @else

        {{-- Customer header --}}
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-zinc-800 dark:text-white">{{ $selectedCustomer->name }}</h2>
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
                    @if($selectedCustomer->phone)
                    <span class="flex items-center gap-1">
                        <flux:icon.phone class="size-3.5" />
                        {{ $selectedCustomer->phone }}
                    </span>
                    @endif
                    @if($selectedCustomer->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $selectedCustomer->whatsapp) }}"
                       target="_blank"
                       class="flex items-center gap-1 text-green-600 hover:text-green-700">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        {{ $selectedCustomer->whatsapp }}
                    </a>
                    @endif
                    @if($selectedCustomer->notes)
                    <span class="text-zinc-400 italic truncate max-w-xs">{{ $selectedCustomer->notes }}</span>
                    @endif
                </div>
            </div>
            <div class="flex gap-1 shrink-0">
                <flux:button wire:click="editCustomer({{ $selectedCustomer->id }})" size="xs" variant="ghost" icon="pencil-square" />
                <flux:button wire:click="deleteCustomer({{ $selectedCustomer->id }})" wire:confirm="Bu müştərini silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
            </div>
        </div>

        @if(!$canUseRequests)
        {{-- Plan gate --}}
        @include('livewire.partials.plan-gate', ['planKey' => 'requests', 'planName' => 'İstəklər və Uyğunluqlar', 'pageTitle' => 'İstəklər'])

        @else

        {{-- Tabs --}}
        <div class="flex gap-0 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-6">
            @php
                $totalNew = $requests->sum('new_matches_count');
            @endphp
            <button wire:click="$set('rightTab', 'requests')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $rightTab === 'requests'
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                İstəklər
                @if($requests->count() > 0)
                    <span class="ml-1.5 text-xs text-zinc-400">({{ $requests->count() }})</span>
                @endif
            </button>
            <button wire:click="$set('rightTab', 'matches')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors flex items-center gap-2
                    {{ $rightTab === 'matches'
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                Uyğunluqlar
                @if($totalNew > 0)
                    <span class="inline-flex items-center justify-center size-5 rounded-full bg-green-500 text-white text-xs font-bold">{{ $totalNew }}</span>
                @endif
            </button>
        </div>

        {{-- ── İSTƏKLƏR TAB ── --}}
        @if($rightTab === 'requests')
        <div class="flex-1 p-6 overflow-y-auto">
            <div class="flex justify-end mb-4">
                <flux:button wire:click="createRequest" variant="primary" icon="plus" size="sm">Yeni istək</flux:button>
            </div>

            @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 py-16 text-zinc-400">
                <flux:icon.clipboard-document-list class="size-10 opacity-30" />
                <p class="text-sm">Hələ istək yoxdur</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($requests as $req)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ $req->name }}</span>
                                @if($req->is_active)
                                    <flux:badge size="sm" color="green">Aktiv</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">Dayandırılıb</flux:badge>
                                @endif
                            </div>

                            {{-- Filters --}}
                            <div class="mt-2 flex flex-wrap gap-1">
                                @if(!empty($req->filters['categoryId']))
                                    <flux:badge size="sm" color="blue">{{ $categories->firstWhere('bina_id', $req->filters['categoryId'])?->name_az ?? $req->filters['categoryId'] }}</flux:badge>
                                @endif
                                @if(!empty($req->filters['priceMin']) || !empty($req->filters['priceMax']))
                                    <flux:badge size="sm" color="green">{{ $req->filters['priceMin'] ?? '0' }}-{{ $req->filters['priceMax'] ?? '∞' }} AZN</flux:badge>
                                @endif
                                @if(!empty($req->filters['roomMin']) || !empty($req->filters['roomMax']))
                                    <flux:badge size="sm" color="purple">{{ $req->filters['roomMin'] ?? '1' }}-{{ $req->filters['roomMax'] ?? '∞' }} otaq</flux:badge>
                                @endif
                                @if(!empty($req->filters['areaMin']) || !empty($req->filters['areaMax']))
                                    <flux:badge size="sm" color="amber">{{ $req->filters['areaMin'] ?? '0' }}-{{ $req->filters['areaMax'] ?? '∞' }} m²</flux:badge>
                                @endif
                                @if(!empty($req->filters['locationIds']))
                                    <flux:badge size="sm" color="zinc">{{ count($req->filters['locationIds']) }} ərazi</flux:badge>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Matches count --}}
                            <button wire:click="$set('rightTab', 'matches')"
                                class="flex items-center gap-1 hover:opacity-70 transition-opacity">
                                <flux:badge size="sm" color="{{ $req->new_matches_count > 0 ? 'green' : 'zinc' }}">
                                    {{ $req->matches_count }}
                                </flux:badge>
                                @if($req->new_matches_count > 0)
                                    <span class="text-xs text-green-600 font-medium">{{ $req->new_matches_count }} yeni</span>
                                @endif
                            </button>

                            <flux:button wire:click="toggleRequestActive({{ $req->id }})" size="xs" variant="ghost" icon="{{ $req->is_active ? 'pause' : 'play' }}" />
                            <flux:button wire:click="editRequest({{ $req->id }})" size="xs" variant="ghost" icon="pencil-square" />
                            <flux:button wire:click="deleteRequest({{ $req->id }})" wire:confirm="Bu istəyi silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- ── UYĞUNLUQLAR TAB ── --}}
        @if($rightTab === 'matches')
        <div class="flex-1 p-6 overflow-y-auto">
            {{-- Status filter --}}
            <div class="flex gap-1 mb-4">
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

            @if($matches instanceof \Illuminate\Pagination\LengthAwarePaginator ? $matches->isEmpty() : $matches->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 py-16 text-zinc-400">
                <flux:icon.check-badge class="size-10 opacity-30" />
                <p class="text-sm">Bu statusda uyğunluq yoxdur</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($matches as $match)
                <div class="flex gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                    {{-- Photo --}}
                    @if($match->property?->photos && count($match->property->photos) > 0)
                        <img src="{{ $match->property->photos[0] }}" alt="" class="h-16 w-24 rounded-lg object-cover shrink-0" loading="lazy">
                    @else
                        <div class="flex h-16 w-24 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800 text-xs text-zinc-400 shrink-0">Şəkil yox</div>
                    @endif

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">
                                    {{ number_format($match->property?->price) }} {{ $match->property?->currency }}
                                </div>
                                <div class="mt-0.5 text-xs text-zinc-500 flex flex-wrap gap-x-3 gap-y-0.5">
                                    @if($match->property?->rooms)
                                        <span>{{ $match->property->rooms }} otaq</span>
                                    @endif
                                    @if($match->property?->area)
                                        <span>{{ $match->property->area }} m²</span>
                                    @endif
                                    @if($match->property?->location_full_name)
                                        <span class="truncate max-w-48">{{ $match->property->location_full_name }}</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-indigo-500">{{ $match->customerRequest?->name }}</div>
                            </div>
                            <div class="flex gap-1 shrink-0">
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
                        </div>
                        <div class="mt-1 text-xs text-zinc-400">{{ $match->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($matches instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-4">{{ $matches->links() }}</div>
            @endif
            @endif
        </div>
        @endif

        @endif {{-- canUseRequests --}}
        @endif {{-- selectedCustomer --}}
        </div>{{-- wire:loading.remove wrapper --}}
    </div>
</div>

{{-- ════ MODALS ════ --}}


{{-- Customer form --}}
<flux:modal wire:model="showCustomerForm" class="max-w-lg">
    <flux:heading>{{ $editingCustomerId ? 'Müştəri redaktə' : 'Yeni müştəri' }}</flux:heading>
    <form wire:submit="saveCustomer" class="mt-4 space-y-4"
        x-data="{
            wpManuallyEdited: {{ $editingCustomerId && $cWhatsapp ? 'true' : 'false' }},
            maskPhone(el) {
                let val = el.value.replace(/[^\d+]/g, '');
                if (val.length > 1) val = '+' + val.replace(/\+/g, '');
                if (val.length > 20) val = val.slice(0, 20);
                if (el.value !== val) el.value = val;
                return val;
            },
            syncWp(val) {
                if (!this.wpManuallyEdited) $wire.set('cWhatsapp', val);
            }
        }"
    >
        <flux:input wire:model="cName" label="Ad Soyad" required />
        <div class="grid grid-cols-2 gap-4 mb-1">
            <flux:input wire:model="cPhone" label="Mobil nömrə" placeholder="+994501234567"
                x-on:input="syncWp(maskPhone($event.target))" />
            <div>
                <div class="flex items-center gap-1.5 mb-4">
                    <svg class="size-4 text-green-600 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">WhatsApp</label>
                </div>
                <flux:input wire:model="cWhatsapp" placeholder="+994501234567"
                    x-on:input="wpManuallyEdited = true; maskPhone($event.target)" />
            </div>
        </div>
        <flux:textarea wire:model="cNotes" label="Qeydlər" rows="3" />
        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showCustomerForm', false)" variant="ghost">Ləğv et</flux:button>
            <flux:button type="submit" variant="primary">Saxla</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Request form --}}
<flux:modal wire:model="showRequestForm" class="max-w-2xl">
    <flux:heading>{{ $editingRequestId ? 'İstəyi redaktə' : 'Yeni istək' }}</flux:heading>
    <form wire:submit="saveRequest" class="mt-4 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="rName" label="İstək adı" placeholder="Məs: Yasamal 2 otaq" required />
            <flux:select wire:model="rCategoryId" label="Kateqoriya">
                <flux:select.option value="">Hamısı</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->bina_id }}">{{ $cat->name_az }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:heading size="sm" class="mb-1">Ərazilər</flux:heading>
            @if(!empty($rLocationIds))
            <div class="mb-1 flex flex-wrap gap-1">
                @foreach($rLocationIds as $locBinaId)
                    @php($loc = $locations->firstWhere('bina_id', $locBinaId))
                    @if($loc)
                    <flux:badge size="sm">
                        {{ $loc->name_az }}
                        <flux:badge.close wire:click="$set('rLocationIds', {{ json_encode(array_values(array_diff($rLocationIds, [$locBinaId]))) }})" class="cursor-pointer" />
                    </flux:badge>
                    @endif
                @endforeach
            </div>
            @endif
            <flux:select wire:model.live="rLocationIds" multiple searchable placeholder="Ərazi seçin...">
                @foreach($locations as $loc)
                    <flux:select.option value="{{ $loc->bina_id }}">{{ $loc->name_az }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="rPriceMin" label="Min qiymət (AZN)" type="number" />
            <flux:input wire:model="rPriceMax" label="Max qiymət (AZN)" type="number" />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="rRoomMin" label="Min otaq" type="number" min="1" max="10" />
            <flux:input wire:model="rRoomMax" label="Max otaq" type="number" min="1" max="10" />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="rAreaMin" label="Min sahə (m²)" type="number" />
            <flux:input wire:model="rAreaMax" label="Max sahə (m²)" type="number" />
        </div>
        <flux:checkbox wire:model="rIsActive" label="Aktiv" />

        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showRequestForm', false)" variant="ghost">Ləğv et</flux:button>
            <flux:button type="submit" variant="primary">Saxla</flux:button>
        </div>
    </form>
</flux:modal>
</div>

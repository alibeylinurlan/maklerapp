<?php

use App\Jobs\MatchRequestToExistingPropertiesJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Location;
use App\Models\PropertyMatch;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    // Left panel
    public string $search = '';
    public int $customerLimit = 30;
    #[Url] public ?int $selectedCustomerId = null;

    // Right panel
    #[Url] public ?int $selectedRequestId = null;
    #[Url] public string $matchStatus = 'new';

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
    public string $rFloorMin = '';
    public string $rFloorMax = '';
    public string $rAreaMin = '';
    public string $rAreaMax = '';
    public bool $rHasMortgage = false;
    public bool $rHasBillOfSale = false;
    public bool $rNotFirstFloor = false;
    public bool $rNotTopFloor = false;
    public bool $rOnlyTopFloor = false;
    public string $rSearchScope = 'new_only'; // 'new_only' | 'all'
    public bool $rIsActive = true;

    // Match bulk selection
    public array $selectedMatchIds = [];

    // Telegram notify modal
    public bool $showNotifyModal = false;
    public ?int $notifyRequestId = null;
    public string $notifyRequestName = '';
    public bool $notifyCurrentState = false;

    public function updatedSearch(): void { $this->resetPage(); $this->customerLimit = 30; $this->selectedCustomerId = null; }

    public function loadMoreCustomers(): void
    {
        $this->customerLimit += 30;
    }
    public function updatedMatchStatus(): void { $this->selectedMatchIds = []; }


    // ── Customer actions ──────────────────────────────────────────

    public function selectCustomer(int $id): void
    {
        $this->selectedCustomerId = $id;
        $this->selectedRequestId = null;
        $this->matchStatus = 'new';
    }

    public function selectRequest(int $id): void
    {
        $this->selectedRequestId = $this->selectedRequestId === $id ? null : $id;
        $this->matchStatus = 'new';
        $this->selectedMatchIds = [];
        $this->resetPage();
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
        $this->reset(['editingRequestId', 'rName', 'rCategoryId', 'rLocationIds',
            'rPriceMin', 'rPriceMax', 'rRoomMin', 'rRoomMax', 'rFloorMin', 'rFloorMax',
            'rAreaMin', 'rAreaMax', 'rHasMortgage', 'rHasBillOfSale',
            'rNotFirstFloor', 'rNotTopFloor', 'rOnlyTopFloor']);
        $this->rSearchScope = 'new_only';
        $this->rIsActive = true;
        $this->showRequestForm = true;
    }

    public function editRequest(int $id): void
    {
        $req = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $this->editingRequestId = $req->id;
        $this->rName           = $req->name;
        $this->rIsActive       = $req->is_active;
        $f = $req->filters;
        $this->rCategoryId     = $f['categoryId'] ?? '';
        $this->rLocationIds    = array_map('strval', $f['locationIds'] ?? []);
        $this->rPriceMin       = $f['priceMin'] ?? '';
        $this->rPriceMax       = $f['priceMax'] ?? '';
        $this->rRoomMin        = $f['roomMin'] ?? '';
        $this->rRoomMax        = $f['roomMax'] ?? '';
        $this->rFloorMin       = $f['floorMin'] ?? '';
        $this->rFloorMax       = $f['floorMax'] ?? '';
        $this->rAreaMin        = $f['areaMin'] ?? '';
        $this->rAreaMax        = $f['areaMax'] ?? '';
        $this->rHasMortgage    = $f['hasMortgage'] ?? false;
        $this->rHasBillOfSale  = $f['hasBillOfSale'] ?? false;
        $this->rNotFirstFloor  = $f['notFirstFloor'] ?? false;
        $this->rNotTopFloor    = $f['notTopFloor'] ?? false;
        $this->rOnlyTopFloor   = $f['onlyTopFloor'] ?? false;
        $this->rSearchScope    = $f['searchScope'] ?? 'new_only';
        $this->showRequestForm = true;
    }

    public function saveRequest(string $nameFromClient = ''): void
    {
        if ($nameFromClient !== '') $this->rName = $nameFromClient;
        $this->validate(['rName' => 'required|min:2|max:255']);

        $filters = array_filter([
            'categoryId'    => $this->rCategoryId ?: null,
            'locationIds'   => !empty($this->rLocationIds) ? $this->rLocationIds : null,
            'priceMin'      => $this->rPriceMin ? (int) $this->rPriceMin : null,
            'priceMax'      => $this->rPriceMax ? (int) $this->rPriceMax : null,
            'roomMin'       => $this->rRoomMin ? (int) $this->rRoomMin : null,
            'roomMax'       => $this->rRoomMax ? (int) $this->rRoomMax : null,
            'floorMin'      => $this->rFloorMin ? (int) $this->rFloorMin : null,
            'floorMax'      => $this->rFloorMax ? (int) $this->rFloorMax : null,
            'areaMin'       => $this->rAreaMin ? (int) $this->rAreaMin : null,
            'areaMax'       => $this->rAreaMax ? (int) $this->rAreaMax : null,
            'hasMortgage'   => $this->rHasMortgage ?: null,
            'hasBillOfSale' => $this->rHasBillOfSale ?: null,
            'notFirstFloor' => $this->rNotFirstFloor ?: null,
            'notTopFloor'   => $this->rNotTopFloor ?: null,
            'onlyTopFloor'  => $this->rOnlyTopFloor ?: null,
            'searchScope'   => $this->rSearchScope,
        ], fn($v) => $v !== null);

        if ($this->editingRequestId) {
            $existing = CustomerRequest::find($this->editingRequestId);
            if ($existing) {
                $oldFilters = $existing->filters ?? [];
                if (isset($oldFilters['locationIds'])) {
                    $oldFilters['locationIds'] = array_map('strval', $oldFilters['locationIds']);
                }
                $oldFilters = array_filter($oldFilters, fn($v) => $v !== null);
                $compareOld = $oldFilters;
                $compareNew = $filters;
                ksort($compareOld);
                ksort($compareNew);
                if (json_encode($compareOld) !== json_encode($compareNew)) {
                    PropertyMatch::where('customer_request_id', $this->editingRequestId)->delete();
                }
            }
        }

        $req = CustomerRequest::updateOrCreate(
            ['id' => $this->editingRequestId],
            [
                'customer_id' => $this->selectedCustomerId,
                'user_id'     => auth()->id(),
                'name'        => $this->rName,
                'filters'     => $filters,
                'is_active'   => $this->rIsActive,
            ]
        );

        // Bütün elanlar seçimi müvəqqəti deaktivdir
        // if ($req->is_active && ($req->filters['searchScope'] ?? 'new_only') === 'all') {
        //     MatchRequestToExistingPropertiesJob::dispatchSync($req->id);
        // }

        $this->showRequestForm = false;
        $this->selectedRequestId = $req->id;
        $this->matchStatus = 'new';
    }

    public function deleteRequest(int $id): void
    {
        CustomerRequest::where('user_id', auth()->id())->findOrFail($id)->delete();
    }

    public function openNotifyModal(int $id): void
    {
        $req = CustomerRequest::where('user_id', auth()->id())->findOrFail($id);
        $this->notifyRequestId   = $req->id;
        $this->notifyRequestName = $req->name;
        $this->notifyCurrentState = $req->notify_telegram;
        $this->showNotifyModal   = true;
    }

    public function confirmToggleTelegramNotify(): void
    {
        $req = CustomerRequest::where('user_id', auth()->id())->findOrFail($this->notifyRequestId);
        $req->update(['notify_telegram' => !$req->notify_telegram]);
        $this->showNotifyModal = false;
    }

    // ── Match actions ─────────────────────────────────────────────

    public function markViewed(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update(['status' => 'viewed']);
    }

    public function markInProgress(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update(['status' => 'in_progress']);
    }

    public function dismiss(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update([
            'status'       => 'dismissed',
            'dismissed_at' => now(),
        ]);
    }

    public function recover(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)->update([
            'status'       => 'in_progress',
            'dismissed_at' => null,
        ]);
    }

    public function bulkMarkViewed(): void
    {
        if (empty($this->selectedMatchIds)) return;
        PropertyMatch::where('user_id', auth()->id())->whereIn('id', $this->selectedMatchIds)->update(['status' => 'viewed']);
        $this->selectedMatchIds = [];
    }

    public function bulkMarkInProgress(): void
    {
        if (empty($this->selectedMatchIds)) return;
        PropertyMatch::where('user_id', auth()->id())->whereIn('id', $this->selectedMatchIds)->update(['status' => 'in_progress']);
        $this->selectedMatchIds = [];
    }

    public function bulkDismiss(): void
    {
        if (empty($this->selectedMatchIds)) return;
        PropertyMatch::where('user_id', auth()->id())->whereIn('id', $this->selectedMatchIds)->update([
            'status'       => 'dismissed',
            'dismissed_at' => now(),
        ]);
        $this->selectedMatchIds = [];
    }

    public function bulkRecover(): void
    {
        if (empty($this->selectedMatchIds)) return;
        PropertyMatch::where('user_id', auth()->id())->whereIn('id', $this->selectedMatchIds)->update([
            'status'       => 'in_progress',
            'dismissed_at' => null,
        ]);
        $this->selectedMatchIds = [];
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
            ->selectSub(
                \App\Models\PropertyMatch::selectRaw('MAX(created_at)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', 'new'),
                'latest_new_match_at'
            )
            ->with('user:id,name')
            ->orderByRaw('latest_new_match_at IS NULL ASC')
            ->orderByDesc('latest_new_match_at')
            ->orderByDesc('updated_at');

        if ($this->search) {
            $customerQuery->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $customers = $customerQuery->limit($this->customerLimit + 1)->get();
        $hasMoreCustomers = $customers->count() > $this->customerLimit;
        $customers = $customers->take($this->customerLimit);

        // Right panel data
        $selectedCustomer = null;
        $requests = collect();
        $selectedRequest = null;
        $matches = collect();
        $matchCounts = ['new' => 0, 'viewed' => 0, 'in_progress' => 0, 'dismissed' => 0];

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

                if ($this->selectedRequestId) {
                    $selectedRequest = $requests->firstWhere('id', $this->selectedRequestId);

                    if ($selectedRequest) {
                        $matches = PropertyMatch::with('property')
                            ->where('user_id', auth()->id())
                            ->where('customer_request_id', $this->selectedRequestId)
                            ->where('status', $this->matchStatus)
                            ->orderByDesc('created_at')
                            ->paginate(20);

                        $q = PropertyMatch::where('user_id', auth()->id())->where('customer_request_id', $this->selectedRequestId);
                        $matchCounts = [
                            'new'         => (clone $q)->where('status', 'new')->count(),
                            'viewed'      => (clone $q)->where('status', 'viewed')->count(),
                            'in_progress' => (clone $q)->where('status', 'in_progress')->count(),
                            'dismissed'   => (clone $q)->where('status', 'dismissed')->count(),
                        ];
                    }
                }
            }
        }

        return [
            'customers'        => $customers,
            'hasMoreCustomers' => $hasMoreCustomers,
            'hasChildren'      => $hasChildren,
            'selectedCustomer' => $selectedCustomer,
            'canUseRequests'   => $canUseRequests,
            'requests'         => $requests,
            'selectedRequest'  => $selectedRequest,
            'matches'          => $matches,
            'matchCounts'      => $matchCounts,
            'categories'       => Category::where('is_active', true)->get(),
            'locations'        => Location::where('is_active', true)->orderBy('name_az')->get(),
        ];
    }
}; ?>

<div>
<div class="flex flex-col" style="height: calc(100vh - 4rem); gap: 0.625rem;">
<div class="flex gap-0 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-md flex-1 min-h-0">

    {{-- ════ SOL PANEL: MÜŞTƏRİLƏR ════ --}}
    <div class="flex flex-col border-r border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 h-full"
         style="width: 300px; min-width: 300px;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-3 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <span class="font-semibold text-sm text-zinc-700 dark:text-zinc-200">Müştərilərim <em class="text-[10px] font-normal text-zinc-400 italic">(alıcılar)</em></span>
            <flux:button wire:click="createCustomer" variant="primary" icon="plus" size="xs">Yeni</flux:button>
        </div>

        {{-- Search --}}
        <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Axtar..." icon="magnifying-glass" size="sm" />
        </div>

        {{-- Customer list --}}
        <div class="flex-1 overflow-y-auto" x-data
             x-on:scroll.passive="if($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 40) $wire.loadMoreCustomers()">
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
                        <span class="flex flex-col items-end gap-1">
                            @if($customer->new_matches_count > 0)
                                <span class="size-2.5 rounded-full bg-green-500 block"></span>
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
            @if($hasMoreCustomers)
            <div wire:loading.remove wire:target="loadMoreCustomers" class="py-3 text-center text-xs text-zinc-400">
                Daha çox yükləmək üçün aşağı sürüşdürün
            </div>
            <div wire:loading wire:target="loadMoreCustomers" class="py-3 w-full flex justify-center text-xs text-zinc-400">
                Yüklənir...
            </div>
            @endif
        </div>
    </div>

    {{-- ════ SAĞ PANEL: DETAL ════ --}}
    <div class="flex-1 relative flex flex-col min-w-0 bg-zinc-50 dark:bg-zinc-950 h-full overflow-hidden">

        <div wire:loading wire:target="selectCustomer"
             style="position:absolute;inset:0;z-index:20">
            <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:8px">
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0s"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.4s"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.8s"></span>
            </div>
        </div>
        <style>
            @keyframes softBlink {
                0%, 100% { opacity: 0.15; }
                50% { opacity: 0.9; }
            }
        </style>

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
                    <span class="flex items-center gap-1 text-zinc-400">
                        <flux:icon.calendar class="size-3.5" />
                        {{ $selectedCustomer->created_at->format('d.m.Y') }}
                    </span>
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

        {{-- ── SPLIT: İSTƏKLƏR + UYĞUNLUQLAR ── --}}
        <div class="flex flex-1 min-w-0 overflow-hidden">

            {{-- İstəklər siyahısı --}}
            <div class="flex flex-col border-r border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 h-full overflow-hidden transition-all duration-200
                {{ $selectedRequestId ? 'w-72 shrink-0' : 'flex-1' }}">

                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">İstəklər</span>
                    <flux:button wire:click="createRequest" variant="primary" icon="plus" size="xs">Yeni</flux:button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    @if($requests->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 py-16 text-zinc-400">
                        <flux:icon.clipboard-document-list class="size-10 opacity-30" />
                        <p class="text-sm">Hələ istək yoxdur</p>
                    </div>
                    @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($requests as $req)
                        @php $isSelected = $selectedRequestId === $req->id; @endphp
                        <div wire:click="selectRequest({{ $req->id }})" class="px-4 py-3 transition-colors cursor-pointer
                            {{ $isSelected ? 'bg-indigo-50 dark:bg-indigo-950/40 border-l-2 border-l-indigo-500' : 'border-l-2 border-l-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium truncate {{ $isSelected ? 'text-indigo-700 dark:text-indigo-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                                        {{ $req->name }}
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <flux:badge size="sm" color="{{ $req->new_matches_count > 0 ? 'green' : 'zinc' }}">
                                            {{ $req->matches_count }}
                                        </flux:badge>
                                        @if($req->new_matches_count > 0)
                                            <span class="text-xs text-green-600 font-medium">{{ $req->new_matches_count }} yeni</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0" wire:click.stop>
                                    <button type="button" wire:click="openNotifyModal({{ $req->id }})"
                                            title="{{ $req->notify_telegram ? 'Telegram bildirişi aktiv' : 'Telegram bildirişi deaktiv' }}"
                                            class="inline-flex items-center justify-center size-7 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                                        <svg class="size-4 {{ $req->notify_telegram ? 'text-emerald-500' : 'text-zinc-300 dark:text-zinc-600' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                        </svg>
                                    </button>
                                    <flux:button wire:click="editRequest({{ $req->id }})" size="xs" variant="ghost" icon="pencil-square" />
                                    <flux:button wire:click="deleteRequest({{ $req->id }})" wire:confirm="Bu istəyi silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Uyğunluqlar paneli --}}
            @if($selectedRequestId && $selectedRequest)
            <div class="flex-1 relative flex flex-col overflow-hidden">

                <div wire:loading wire:target="selectRequest"
                     style="position:absolute;inset:0;z-index:20">
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:8px">
                        <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0s"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.4s"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.8s"></span>
                    </div>
                </div>

                {{-- Header --}}
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200 truncate">{{ $selectedRequest->name }}</span>
                    <button wire:click="selectRequest({{ $selectedRequestId }})" class="text-zinc-400 hover:text-zinc-600 shrink-0">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                {{-- Status tabs --}}
                <div class="flex gap-1 px-4 py-2 border-b border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                    @foreach(['new' => 'Yeni', 'viewed' => 'Baxılıb', 'in_progress' => 'Nəzarətdə', 'dismissed' => 'Silindi'] as $status => $label)
                    <flux:button wire:click="$set('matchStatus', '{{ $status }}')"
                        variant="{{ $matchStatus === $status ? 'primary' : 'ghost' }}" size="xs">
                        {{ $label }} ({{ $matchCounts[$status] }})
                    </flux:button>
                    @endforeach
                </div>

                {{-- Tab description --}}
                @php
                    $tabDescriptions = [
                        'new'         => 'Yeni tapılan uyğunluqlar',
                        'viewed'      => 'Gedib baxmısız bu elanlara',
                        'in_progress' => 'Bu elanlar sizin diqqət mərkəzinizdədir. Danışıqlar gedir.',
                        'dismissed'   => 'Burda sildiyiniz uyğunluqları görürsünüz. 1 saat sonra avtomatik silinəcək.',
                    ];
                @endphp
                <div class="px-4 py-2 border-b border-zinc-100 dark:border-zinc-800">
                    <p class="text-xs text-zinc-400">{{ $tabDescriptions[$matchStatus] }}</p>
                </div>

                {{-- Bulk action bar --}}
                @if(count($selectedMatchIds) > 0)
                <div class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-950/40 border-b border-indigo-200 dark:border-indigo-800">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ count($selectedMatchIds) }} seçilib</span>
                    <div class="flex gap-1 ml-auto">
                        @if($matchStatus !== 'viewed')
                        <flux:button wire:click="bulkMarkViewed" size="xs" variant="ghost" icon="eye">Baxıldı</flux:button>
                        @endif
                        @if($matchStatus !== 'in_progress')
                        <flux:button wire:click="bulkMarkInProgress" size="xs" variant="ghost" icon="clock">Nəzarətdə</flux:button>
                        @endif
                        @if($matchStatus === 'dismissed')
                        <flux:button wire:click="bulkRecover" size="xs" variant="ghost" icon="arrow-uturn-left" class="text-green-600">Bərpa et</flux:button>
                        @else
                        <flux:button wire:click="bulkDismiss" size="xs" variant="ghost" icon="x-mark" class="text-red-500">Sil</flux:button>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Match list --}}
                <div class="flex-1 overflow-y-auto p-4">
                    @if($matches->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 py-16 text-zinc-400">
                        <flux:icon.check-badge class="size-10 opacity-30" />
                        <p class="text-sm">Bu statusda uyğunluq yoxdur</p>
                    </div>
                    @else
                    <div class="space-y-3">
                        @foreach($matches as $match)
                        <div class="flex gap-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3
                            {{ in_array($match->id, $selectedMatchIds) ? 'ring-2 ring-indigo-400' : '' }}">

                            {{-- Checkbox --}}
                            <div class="flex items-start pt-1">
                                <input type="checkbox" wire:model.live="selectedMatchIds" value="{{ $match->id }}"
                                       class="size-4 rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 cursor-pointer">
                            </div>

                            {{-- Photo --}}
                            @php($thumb = $match->property?->photos[0]['thumb'] ?? $match->property?->photos[0]['medium'] ?? null)
                            @if($thumb)
                                <img src="{{ $thumb }}" alt="" class="h-16 w-24 rounded-lg object-cover shrink-0" loading="lazy">
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
                                    </div>
                                    <div class="flex gap-1 shrink-0">
                                        <a href="{{ $match->property?->full_url }}" target="_blank">
                                            <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" />
                                        </a>
                                        @if($match->status !== 'viewed')
                                            <flux:button wire:click="markViewed({{ $match->id }})" size="xs" variant="ghost" icon="eye" title="Baxıldı" />
                                        @endif
                                        @if($match->status !== 'in_progress')
                                            <flux:button wire:click="markInProgress({{ $match->id }})" size="xs" variant="ghost" icon="clock" title="Nəzarətdə" />
                                        @endif
                                        @if($match->status === 'dismissed')
                                            <flux:button wire:click="recover({{ $match->id }})" size="xs" variant="ghost" icon="arrow-uturn-left" class="text-green-600" title="Bərpa et" />
                                        @else
                                            <flux:button wire:click="dismiss({{ $match->id }})" size="xs" variant="ghost" icon="x-mark" class="text-red-500" title="Sil" />
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-1 text-xs text-zinc-400">{{ ($match->property?->bumped_at ?? $match->property?->created_at)?->diffForHumans() }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4">{{ $matches->links() }}</div>
                    @endif
                </div>
            </div>
            @elseif($selectedRequestId && !$selectedRequest)
                {{-- selectedRequestId var amma request tapılmadı (silinib) --}}
                @php($this->selectedRequestId = null)
            @endif

        </div>{{-- end split --}}

        @endif {{-- canUseRequests --}}
        @endif {{-- selectedCustomer --}}
        </div>{{-- wire:loading.remove wrapper --}}
    </div>
</div>{{-- end 3-panel --}}

{{-- ════ CANLI ELANLAR (horizontal) ════ --}}
<div class="rounded-2xl overflow-hidden border border-white/10 shadow-xl shrink-0"
     style="min-height: 170px; background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);">
    @livewire('properties.live-feed-horizontal')
</div>

</div>{{-- end flex-col wrapper --}}

{{-- ════ MODALS ════ --}}


{{-- Customer form --}}
<flux:modal wire:model="showCustomerForm" class="max-w-lg">
    <flux:heading>{{ $editingCustomerId ? 'Müştəri redaktə' : 'Yeni müştəri' }}</flux:heading>
    <form wire:submit="saveCustomer" class="mt-4 space-y-4"
        x-data="{
            wpManuallyEdited: {{ $editingCustomerId && $cWhatsapp ? 'true' : 'false' }},
            maskPhone(el) {
                let val = el.value.replace(/[^\d+]/g, '');
                val = val.replace(/^\++/, '');
                if (val.startsWith('994')) val = '+' + val;
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
<flux:modal wire:model="showRequestForm" class="max-w-3xl">
    <flux:heading>{{ $editingRequestId ? 'İstəyi redaktə' : 'Yeni istək' }}</flux:heading>
    <form class="mt-4 space-y-4"
          x-data="{
              autoName: {{ $editingRequestId ? 'false' : 'true' }},
              localName: @js($rName),
              cats: @js($categories->pluck('name_az', 'id')),
              locs: @js($locations->pluck('name_az', 'id')),

              init() {
                  // Modal açılanda Livewire-dan adı sinxronlaşdır
                  $wire.$watch('showRequestForm', (open) => {
                      if (open) {
                          this.localName = $wire.rName || '';
                          this.autoName  = !$wire.editingRequestId;
                      }
                  });
                  // Filter dəyişəndə adı yenilə (yalnız auto rejimdə)
                  const watched = ['rCategoryId','rLocationIds','rRoomMin','rRoomMax',
                                   'rPriceMin','rPriceMax','rFloorMin','rFloorMax',
                                   'rAreaMin','rAreaMax','rHasMortgage','rHasBillOfSale',
                                   'rNotFirstFloor','rNotTopFloor','rOnlyTopFloor'];
                  watched.forEach(p => $wire.$watch(p, () => { if (this.autoName) this.generate(); }));
              },

              generate() {
                  const parts = [];
                  const catId = $wire.rCategoryId;
                  if (catId && this.cats[catId]) parts.push(this.cats[catId]);

                  const locIds = $wire.rLocationIds || [];
                  const locNames = locIds.map(id => this.locs[id]).filter(Boolean);
                  if (locNames.length) parts.push(locNames.join(', '));

                  const rMin = $wire.rRoomMin, rMax = $wire.rRoomMax;
                  if (rMin || rMax) {
                      if (rMin && rMax) parts.push(rMin == rMax ? rMin+' otaq' : rMin+'-'+rMax+' otaq');
                      else if (rMin) parts.push(rMin+'+ otaq');
                      else parts.push(rMax+' otaq');
                  }

                  const pMin = $wire.rPriceMin, pMax = $wire.rPriceMax;
                  if (pMin || pMax) {
                      const fmt = n => Number(n).toLocaleString('az-AZ');
                      if (pMin && pMax) parts.push(fmt(pMin)+'-'+fmt(pMax)+' AZN');
                      else if (pMax) parts.push(fmt(pMax)+' AZN-ə qədər');
                      else parts.push(fmt(pMin)+' AZN-dən');
                  }

                  const fMin = $wire.rFloorMin, fMax = $wire.rFloorMax;
                  if (fMin || fMax) {
                      if (fMin && fMax) parts.push(fMin+'-'+fMax+' mərtəbə');
                      else if (fMin) parts.push(fMin+'+ mərtəbə');
                      else parts.push(fMax+' mərtəbəyə qədər');
                  }

                  const aMin = $wire.rAreaMin, aMax = $wire.rAreaMax;
                  if (aMin || aMax) {
                      if (aMin && aMax) parts.push(aMin+'-'+aMax+' m²');
                      else if (aMin) parts.push(aMin+'+ m²');
                      else parts.push(aMax+' m²-ə qədər');
                  }

                  const flags = [];
                  if ($wire.rHasBillOfSale) flags.push('çıxarış');
                  if ($wire.rHasMortgage)   flags.push('ipoteka');
                  if ($wire.rNotFirstFloor) flags.push('1-ci olmasın');
                  if ($wire.rNotTopFloor)   flags.push('ən üst olmasın');
                  if ($wire.rOnlyTopFloor)  flags.push('yalnız ən üst');
                  if (flags.length) parts.push(flags.join(', '));

                  this.localName = parts.join(', ');
              },

              resetAuto() {
                  this.autoName = true;
                  this.generate();
              },

              handleSubmit() {
                  $wire.saveRequest(this.localName);
              }
          }"
          @submit.prevent="handleSubmit()">

        {{-- İstək adı --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <flux:label>İstək adı</flux:label>
                <span x-show="autoName" class="flex items-center gap-1 text-xs text-emerald-500">
                    <flux:icon.sparkles class="size-3" />
                    Avtomatik
                </span>
                <button x-show="!autoName" type="button" @click="resetAuto()"
                        class="flex items-center gap-1 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                    <flux:icon.arrow-path class="size-3" />
                    Avtomatik
                </button>
            </div>
            <flux:input x-model="localName" @input="autoName = false"
                        placeholder="Filtrləri seçin..." required />
        </div>

        {{-- Axtarış əhatəsi (müvəqqəti deaktiv) --}}
        {{-- <div>
            <flux:label class="mb-1.5">Axtarış əhatəsi</flux:label>
            <div class="flex gap-3">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition"
                       :class="$wire.rSearchScope === 'new_only' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/40' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300'">
                    <input type="radio" wire:model.live="rSearchScope" value="new_only" class="text-indigo-600">
                    <div>
                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Yalnız yeni paylaşılanlar</div>
                        <div class="text-xs text-zinc-500">İstək yaradıldıqdan sonra gələn elanlar</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition"
                       :class="$wire.rSearchScope === 'all' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/40' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300'">
                    <input type="radio" wire:model.live="rSearchScope" value="all" class="text-indigo-600">
                    <div>
                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Bütün elanlar</div>
                        <div class="text-xs text-zinc-500">Mövcud + yeni paylaşılanlar</div>
                    </div>
                </label>
            </div>
        </div> --}}

        @include('livewire.partials.filter-fields', [
            'categories' => $categories,
            'locations'  => $locations,
            'f' => [
                'category'      => 'rCategoryId',
                'locationIds'   => 'rLocationIds',
                'roomMin'       => 'rRoomMin',
                'roomMax'       => 'rRoomMax',
                'priceMin'      => 'rPriceMin',
                'priceMax'      => 'rPriceMax',
                'floorMin'      => 'rFloorMin',
                'floorMax'      => 'rFloorMax',
                'areaMin'       => 'rAreaMin',
                'areaMax'       => 'rAreaMax',
                'hasMortgage'   => 'rHasMortgage',
                'hasBillOfSale' => 'rHasBillOfSale',
                'notFirstFloor' => 'rNotFirstFloor',
                'notTopFloor'   => 'rNotTopFloor',
                'onlyTopFloor'  => 'rOnlyTopFloor',
            ],
        ])

        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showRequestForm', false)" variant="ghost">Ləğv et</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveRequest">
                <span wire:loading.remove wire:target="saveRequest">Saxla</span>
                <span wire:loading wire:target="saveRequest">Saxlanır...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal wire:model="showNotifyModal" class="max-w-sm">
    <flux:heading size="lg" class="flex items-center gap-2">
        <svg class="size-5 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
        </svg>
        Telegram bildirişi
    </flux:heading>

    <flux:subheading class="mt-2">
        <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $notifyRequestName }}</span>
        istəyi üçün Telegram bildirişini
        <span class="{{ $notifyCurrentState ? 'text-red-500' : 'text-emerald-600' }} font-medium">
            {{ $notifyCurrentState ? 'deaktiv' : 'aktiv' }}
        </span>
        etmək istəyirsiniz?
    </flux:subheading>

    <div class="flex justify-end gap-2 mt-6">
        <flux:button wire:click="$set('showNotifyModal', false)" variant="ghost">Ləğv et</flux:button>
        <flux:button wire:click="confirmToggleTelegramNotify" variant="{{ $notifyCurrentState ? 'danger' : 'primary' }}">
            {{ $notifyCurrentState ? 'Deaktiv et' : 'Aktiv et' }}
        </flux:button>
    </div>
</flux:modal>
</div>

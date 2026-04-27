<?php

use App\Models\Seller;
use App\Models\SellerProperty;
use App\Services\BinaAz\BinaAzUrlScraper;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;

new class extends Component {

    public bool $canAccess = false;
    public int $sellerLimit = 30;
    public string $search = '';
    #[Url] public ?int $selectedSellerId = null;

    // Seller form
    public bool $showSellerForm = false;
    public ?int $editingSellerId = null;
    public string $sName = '';
    public string $sPhone = '+994';
    public string $sWhatsapp = '';
    public string $sNotes = '';

    // Property form
    public bool $showPropertyForm = false;
    public ?int $editingPropertyId = null;
    public string $pNotes = '';
    public string $pLink = '';
    public bool $pScraping = false;
    public string $pScrapeError = '';

    // Scraped data (populated after scrape)
    public ?float $pPrice = null;
    public ?string $pCurrency = 'AZN';
    public ?int $pRooms = null;
    public ?float $pArea = null;
    public ?int $pFloor = null;
    public ?int $pFloorTotal = null;
    public ?string $pTitle = null;
    public ?int $pCategoryId = null;
    public ?int $pLocationId = null;
    public array $pPhotos = [];

    public function updatedSearch(): void
    {
        $this->sellerLimit = 30;
        $this->selectedSellerId = null;
    }

    public function loadMoreSellers(): void
    {
        $this->sellerLimit += 30;
    }

    // ── Seller actions ────────────────────────────────────────────

    public function selectSeller(int $id): void
    {
        $this->selectedSellerId = $id;
    }

    public function createSeller(): void
    {
        $this->reset(['editingSellerId', 'sName', 'sPhone', 'sWhatsapp', 'sNotes']);
        $this->sPhone = '+994';
        $this->showSellerForm = true;
    }

    public function editSeller(int $id): void
    {
        $s = Seller::where('user_id', auth()->id())->findOrFail($id);
        $this->editingSellerId = $s->id;
        $this->sName     = $s->name;
        $this->sPhone    = $s->phone ?? '+994';
        $this->sWhatsapp = $s->whatsapp ?? '';
        $this->sNotes    = $s->notes ?? '';
        $this->showSellerForm = true;
    }

    public function saveSeller(): void
    {
        $this->validate([
            'sName'     => 'required|min:2|max:255',
            'sPhone'    => 'nullable|max:20',
            'sWhatsapp' => 'nullable|max:20',
        ]);

        $seller = Seller::updateOrCreate(
            ['id' => $this->editingSellerId],
            [
                'user_id'  => auth()->id(),
                'name'     => $this->sName,
                'phone'    => $this->sPhone ?: null,
                'whatsapp' => $this->sWhatsapp ?: null,
                'notes'    => $this->sNotes ?: null,
            ]
        );
        $seller->touchActivity();

        $this->selectedSellerId = $seller->id;
        $this->showSellerForm = false;
    }

    public function deleteSeller(int $id): void
    {
        Seller::where('user_id', auth()->id())->findOrFail($id)->delete();
        if ($this->selectedSellerId === $id) {
            $this->selectedSellerId = null;
        }
    }

    // ── Property actions ──────────────────────────────────────────

    public function createProperty(): void
    {
        $this->reset(['editingPropertyId', 'pNotes', 'pLink', 'pScrapeError',
            'pPrice', 'pCurrency', 'pRooms', 'pArea', 'pFloor', 'pFloorTotal',
            'pTitle', 'pCategoryId', 'pLocationId', 'pPhotos']);
        $this->pCurrency = 'AZN';
        $this->showPropertyForm = true;
    }

    public function editProperty(int $id): void
    {
        $prop = SellerProperty::where('user_id', auth()->id())->findOrFail($id);
        $this->editingPropertyId = $prop->id;
        $this->pNotes       = $prop->notes ?? '';
        $this->pLink        = $prop->bina_url ?? '';
        $this->pPrice       = $prop->price;
        $this->pCurrency    = $prop->currency ?? 'AZN';
        $this->pRooms       = $prop->rooms;
        $this->pArea        = $prop->area;
        $this->pFloor       = $prop->floor;
        $this->pFloorTotal  = $prop->floor_total;
        $this->pTitle       = $prop->title;
        $this->pCategoryId  = $prop->category_id;
        $this->pLocationId  = $prop->location_id;
        $this->pPhotos      = $prop->photos ?? [];
        $this->pScrapeError = '';
        $this->showPropertyForm = true;
    }

    public function scrapeLink(): void
    {
        $this->pScrapeError = '';
        if (!$this->pLink) return;

        try {
            $scraper = new BinaAzUrlScraper();
            $data = $scraper->scrape($this->pLink);

            $this->pPrice       = isset($data['price']) ? (float) $data['price'] : null;
            $this->pCurrency    = $data['currency'] ?? 'AZN';
            $this->pRooms       = isset($data['rooms']) ? (int) $data['rooms'] : null;
            $this->pArea        = isset($data['area']) ? (float) $data['area'] : null;
            $this->pFloor       = isset($data['floor']) ? (int) $data['floor'] : null;
            $this->pFloorTotal  = isset($data['floor_total']) ? (int) $data['floor_total'] : null;
            $this->pTitle       = $data['title'] ?? null;
            $this->pCategoryId  = $data['category_id'] ?? null;
            $this->pLocationId  = $data['location_id'] ?? null;
            $this->pPhotos      = $data['photos'] ?? [];

            if (empty($data)) {
                $this->pScrapeError = 'Məlumat tapılmadı. Linki yoxlayın.';
            }
        } catch (\Exception $e) {
            $this->pScrapeError = 'Scrape xətası: ' . $e->getMessage();
        }
    }

    public function saveProperty(): void
    {
        $isBinaLink = $this->pLink && str_contains($this->pLink, 'bina.az');

        $prop = SellerProperty::updateOrCreate(
            ['id' => $this->editingPropertyId],
            [
                'seller_id'   => $this->selectedSellerId,
                'user_id'     => auth()->id(),
                'title'       => $this->pTitle,
                'source'      => $isBinaLink ? 'bina_link' : ($this->pLink ? 'other_link' : 'manual'),
                'bina_url'    => $this->pLink ?: null,
                'category_id' => $this->pCategoryId,
                'location_id' => $this->pLocationId,
                'price'       => $this->pPrice,
                'currency'    => $this->pCurrency ?? 'AZN',
                'rooms'       => $this->pRooms,
                'area'        => $this->pArea,
                'floor'       => $this->pFloor,
                'floor_total' => $this->pFloorTotal,
                'notes'       => $this->pNotes ?: null,
                'photos'      => !empty($this->pPhotos) ? $this->pPhotos : null,
            ]
        );

        $prop->recordPrice();
        Seller::find($this->selectedSellerId)?->touchActivity();
        $this->showPropertyForm = false;
    }

    public function deleteProperty(int $id): void
    {
        SellerProperty::where('user_id', auth()->id())->findOrFail($id)->delete();
        Seller::find($this->selectedSellerId)?->touchActivity();
    }

    public function refreshProperty(int $id): void
    {
        $prop = SellerProperty::where('user_id', auth()->id())->findOrFail($id);
        if (!$prop->bina_url) return;

        try {
            $data = (new BinaAzUrlScraper())->scrape($prop->bina_url);
            $prop->update(array_filter([
                'title'       => $data['title'] ?? $prop->title,
                'price'       => $data['price'] ?? null,
                'currency'    => $data['currency'] ?? $prop->currency,
                'rooms'       => $data['rooms'] ?? null,
                'area'        => $data['area'] ?? null,
                'floor'       => $data['floor'] ?? null,
                'floor_total' => $data['floor_total'] ?? null,
                'photos'      => !empty($data['photos']) ? $data['photos'] : $prop->photos,
                'location_id' => $data['location_id'] ?? $prop->location_id,
                'category_id' => $data['category_id'] ?? $prop->category_id,
            ], fn($v) => $v !== null));
            $prop->refresh()->recordPrice();
        } catch (\Exception $e) {
            // silent fail
        }
    }

    // ── Data ──────────────────────────────────────────────────────

    public function with(): array
    {
        $user = auth()->user();
        $this->canAccess = $user->hasAnyRole(['superadmin', 'admin', 'developer']) || $user->hasFeature('customers');

        if (!$this->canAccess) {
            return ['canAccess' => false, 'sellers' => collect(), 'hasMoreSellers' => false,
                    'selectedSeller' => null, 'properties' => collect()];
        }

        $userId = auth()->id();

        $sellerQuery = Seller::where('user_id', $userId)
            ->withCount('properties')
            ->orderByDesc('last_activity_at');

        if ($this->search) {
            $sellerQuery->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        $sellers = $sellerQuery->limit($this->sellerLimit + 1)->get();
        $hasMoreSellers = $sellers->count() > $this->sellerLimit;
        $sellers = $sellers->take($this->sellerLimit);

        $selectedSeller = null;
        $properties = collect();

        if ($this->selectedSellerId) {
            $selectedSeller = Seller::where('user_id', $userId)->find($this->selectedSellerId);
            if ($selectedSeller) {
                $properties = SellerProperty::where('seller_id', $this->selectedSellerId)
                    ->where('user_id', $userId)
                    ->with(['category', 'location', 'priceHistory'])
                    ->orderByDesc('created_at')
                    ->get();
            }
        }

        return [
            'sellers'        => $sellers,
            'hasMoreSellers' => $hasMoreSellers,
            'selectedSeller' => $selectedSeller,
            'properties'     => $properties,
        ];
    }
}; ?>

<div>
@if(!$canAccess)
    @include('livewire.partials.plan-gate', ['pageTitle' => 'Müştərilər (satıcılar)', 'planName' => 'Giriş tarifi və ya yuxarı'])
@else
<div class="flex flex-col" style="height: calc(100vh - 4rem); gap: 0.625rem;">
<div class="flex gap-0 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-md flex-1 min-h-0">

    {{-- ════ SOL PANEL ════ --}}
    <div class="flex flex-col border-r border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 h-full"
         style="width: 300px; min-width: 300px;">

        <div class="flex items-center justify-between px-3 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <span class="font-semibold text-sm text-zinc-700 dark:text-zinc-200">Müştərilərim <em class="text-[10px] font-normal text-zinc-400 italic">(satıcılar)</em></span>
            <flux:button wire:click="createSeller" variant="primary" icon="plus" size="xs">Yeni</flux:button>
        </div>

        <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.600ms="search" placeholder="Axtar..." icon="magnifying-glass" size="sm" />
        </div>

        <div class="flex-1 overflow-y-auto" x-data
             x-on:scroll.passive="if($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 40) $wire.loadMoreSellers()">
            @forelse($sellers as $seller)
            @php $isSelected = $selectedSellerId === $seller->id; @endphp
            <button
                wire:click="selectSeller({{ $seller->id }})"
                class="w-full text-left px-3 py-3 border-b border-zinc-100 dark:border-zinc-800 transition-colors
                    {{ $isSelected
                        ? 'bg-indigo-50 dark:bg-indigo-950/40 border-l-2 border-l-indigo-500'
                        : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50 border-l-2 border-l-transparent' }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-sm truncate {{ $isSelected ? 'text-indigo-700 dark:text-indigo-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                            {{ $seller->name }}
                        </div>
                        @if($seller->phone)
                        <div class="text-xs text-zinc-500 mt-0.5">{{ $seller->phone }}</div>
                        @endif
                    </div>
                    @if($seller->properties_count > 0)
                    <span class="text-xs text-zinc-400 shrink-0">{{ $seller->properties_count }} mülk</span>
                    @endif
                </div>
            </button>
            @empty
            <div class="px-4 py-8 text-center text-sm text-zinc-400">Satıcı tapılmadı</div>
            @endforelse
            @if($hasMoreSellers)
            <div wire:loading.remove wire:target="loadMoreSellers" class="py-3 text-center text-xs text-zinc-400">Daha çox üçün aşağı sürüşdürün</div>
            <div wire:loading wire:target="loadMoreSellers" class="py-3 w-full flex justify-center text-xs text-zinc-400">Yüklənir...</div>
            @endif
        </div>
    </div>

    {{-- ════ SAĞ PANEL ════ --}}
    <div class="flex-1 relative flex flex-col min-w-0 bg-zinc-50 dark:bg-zinc-950 h-full overflow-hidden">

        <div wire:loading wire:target="selectSeller" style="position:absolute;inset:0;z-index:20">
            <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:8px">
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0s"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.4s"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.8s"></span>
            </div>
        </div>
        <style>@keyframes softBlink { 0%,100%{opacity:.15} 50%{opacity:.9} }</style>

        <div wire:loading.remove wire:target="selectSeller" class="flex flex-col h-full overflow-hidden">
        @if(!$selectedSeller)
        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-zinc-400">
            <flux:icon.user-group class="size-12 opacity-30" />
            <p class="text-sm">Satıcı seçin</p>
        </div>

        @else

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-zinc-800 dark:text-white">{{ $selectedSeller->name }}</h2>
                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
                    @if($selectedSeller->phone)
                    <span class="flex items-center gap-1"><flux:icon.phone class="size-3.5" />{{ $selectedSeller->phone }}</span>
                    @endif
                    @if($selectedSeller->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $selectedSeller->whatsapp) }}" target="_blank"
                       class="flex items-center gap-1 text-green-600 hover:text-green-700">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        {{ $selectedSeller->whatsapp }}
                    </a>
                    @endif
                    @if($selectedSeller->notes)
                    <span class="text-zinc-400 italic truncate max-w-xs">{{ $selectedSeller->notes }}</span>
                    @endif
                    <span class="flex items-center gap-1 text-zinc-400">
                        <flux:icon.calendar class="size-3.5" />{{ $selectedSeller->created_at->format('d.m.Y') }}
                    </span>
                </div>
            </div>
            <div class="flex gap-1 shrink-0">
                <flux:button wire:click="editSeller({{ $selectedSeller->id }})" size="xs" variant="ghost" icon="pencil-square" />
                <flux:button wire:click="deleteSeller({{ $selectedSeller->id }})" wire:confirm="Bu satıcını silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
            </div>
        </div>

        {{-- Properties --}}
        <div class="flex flex-col flex-1 min-h-0 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Mülklər</span>
                <flux:button wire:click="createProperty" variant="primary" icon="plus" size="xs">Mülk əlavə et</flux:button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                @if($properties->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 py-16 text-zinc-400">
                    <flux:icon.home class="size-10 opacity-30" />
                    <p class="text-sm">Hələ mülk əlavə edilməyib</p>
                </div>
                @else
                <div class="grid grid-cols-4 gap-3">
                    @foreach($properties as $prop)
                    @php $thumb = $prop->photos[0]['thumb'] ?? $prop->photos[0]['medium'] ?? null; @endphp
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden flex flex-col">

                        {{-- Photo --}}
                        @if($thumb)
                        <div class="relative">
                            <img src="{{ $thumb }}" alt="" class="w-full h-36 object-cover">
                            @if($prop->bina_url)
                            <a href="{{ $prop->bina_url }}" target="_blank"
                               class="absolute top-2 right-2 flex items-center justify-center size-7 rounded-lg bg-black/50 text-white hover:bg-black/70 transition">
                                <flux:icon.arrow-top-right-on-square class="size-3.5" />
                            </a>
                            @endif
                        </div>
                        @elseif($prop->bina_url)
                        <div class="flex items-center justify-center h-20 bg-sky-50 dark:bg-sky-900/20">
                            <a href="{{ $prop->bina_url }}" target="_blank"
                               class="flex items-center gap-1.5 text-xs text-sky-600 hover:underline">
                                <flux:icon.arrow-top-right-on-square class="size-3.5" />
                                Linkə bax
                            </a>
                        </div>
                        @else
                        <div class="flex items-center justify-center h-20 bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon.home class="size-8 text-zinc-300 dark:text-zinc-600" />
                        </div>
                        @endif

                        {{-- Info --}}
                        <div class="p-3 flex-1 flex flex-col">
                            @if($prop->price)
                            <div class="font-semibold text-sm text-zinc-800 dark:text-zinc-100">
                                {{ number_format($prop->price) }} {{ $prop->currency }}
                            </div>
                            @endif
                            @php
                                $ph = $prop->priceHistory;
                                $first = $ph->first();
                                $last  = $ph->last();
                                $hasChange = $ph->count() > 1 && (float)$first->price !== (float)$last->price;
                            @endphp
                            @if($hasChange)
                            <div class="mt-1 space-y-0.5 text-xs">
                                <div class="text-zinc-400">İlk: <span class="text-zinc-500">{{ number_format($first->price) }} {{ $first->currency }}</span> <span class="text-zinc-300">· {{ \Carbon\Carbon::parse($first->recorded_at)->format('d.m.Y') }}</span></div>
                                <div class="text-zinc-400">Yeniləndi: <span class="{{ (float)$last->price > (float)$first->price ? 'text-red-500' : 'text-green-600' }} font-medium">{{ number_format($last->price) }} {{ $last->currency }}</span> <span class="text-zinc-300">· {{ \Carbon\Carbon::parse($last->recorded_at)->format('d.m.Y') }}</span></div>
                            </div>
                            @elseif($first)
                            <div class="mt-0.5 text-xs text-zinc-400">İlk: {{ \Carbon\Carbon::parse($first->recorded_at)->format('d.m.Y') }}</div>
                            @endif

                            <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-xs text-zinc-500">
                                @if($prop->rooms)<span>{{ $prop->rooms }} otaq</span>@endif
                                @if($prop->area)<span>{{ $prop->area }} m²</span>@endif
                                @if($prop->floor)<span>{{ $prop->floor }}{{ $prop->floor_total ? '/'.$prop->floor_total : '' }} mərtəbə</span>@endif
                            </div>

                            @if($prop->location)
                            <div class="mt-1 text-xs text-zinc-400 truncate">{{ $prop->location->name_az }}</div>
                            @elseif($prop->title)
                            <div class="mt-1 text-xs text-zinc-400 truncate">{{ $prop->title }}</div>
                            @endif

                            @if($prop->notes)
                            <p class="mt-1.5 text-xs text-zinc-400 italic line-clamp-2">{{ $prop->notes }}</p>
                            @endif

                            <div class="mt-auto pt-2 flex items-center justify-between">
                                <span class="text-xs text-zinc-400">{{ $prop->created_at->diffForHumans() }}</span>
                                <div class="flex gap-1">
                                    @if($prop->bina_url)
                                    <flux:button wire:click="refreshProperty({{ $prop->id }})" wire:loading.attr="disabled" wire:target="refreshProperty({{ $prop->id }})" size="xs" variant="ghost" icon="arrow-path" title="Yenilə" />
                                    @endif
                                    <flux:button wire:click="editProperty({{ $prop->id }})" size="xs" variant="ghost" icon="pencil-square" />
                                    <flux:button wire:click="deleteProperty({{ $prop->id }})" wire:confirm="Bu mülkü silmək istəyirsiniz?" size="xs" variant="ghost" icon="trash" class="text-red-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        @endif
        </div>
    </div>
</div>

{{-- Live feed --}}
<div class="rounded-2xl overflow-hidden border border-white/10 shadow-xl shrink-0"
     style="min-height: 170px; background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);">
    <x-live-feed-horizontal />
</div>

</div>

{{-- ════ MODALS ════ --}}

{{-- Seller form --}}
<flux:modal wire:model="showSellerForm" class="max-w-lg">
    <flux:heading>{{ $editingSellerId ? 'Satıcı redaktə' : 'Yeni satıcı' }}</flux:heading>
    <form wire:submit="saveSeller" class="mt-4 space-y-4"
        x-data="{
            wpManuallyEdited: {{ $editingSellerId && $sWhatsapp ? 'true' : 'false' }},
            maskPhone(el) {
                let val = el.value.replace(/[^\d+]/g, '');
                val = val.replace(/^\++/, '');
                if (val.startsWith('994')) val = '+' + val;
                if (val.length > 20) val = val.slice(0, 20);
                if (el.value !== val) el.value = val;
                return val;
            },
            syncWp(val) { if (!this.wpManuallyEdited) $wire.set('sWhatsapp', val); }
        }">
        <flux:input wire:model="sName" label="Ad Soyad" required />
        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="sPhone" label="Mobil nömrə" placeholder="+994501234567"
                x-on:input="syncWp(maskPhone($event.target))" />
            <div>
                <div class="flex items-center gap-1.5 mb-4">
                    <svg class="size-4 text-green-600 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">WhatsApp</label>
                </div>
                <flux:input wire:model="sWhatsapp" placeholder="+994501234567"
                    x-on:input="wpManuallyEdited = true; maskPhone($event.target)" />
            </div>
        </div>
        <flux:textarea wire:model="sNotes" label="Qeydlər" rows="3" />
        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showPropertyForm', false)" variant="ghost">Ləğv et</flux:button>
            <flux:button type="submit" variant="primary">Saxla</flux:button>
        </div>
    </form>
</flux:modal>

{{-- Property form --}}
<flux:modal wire:model="showPropertyForm" class="max-w-lg">
    <flux:heading>{{ $editingPropertyId ? 'Mülkü redaktə et' : 'Mülk əlavə et' }}</flux:heading>
    <form wire:submit="saveProperty" class="mt-4 space-y-4">

        {{-- Link input with scrape button --}}
        <div>
            <flux:label>Bina.az linki və ya başqa link</flux:label>
            <div class="flex gap-2 mt-1">
                <flux:input wire:model="pLink" placeholder="https://bina.az/..." class="flex-1" />
                <flux:button wire:click="scrapeLink" wire:loading.attr="disabled" wire:target="scrapeLink"
                    type="button" variant="ghost" icon="arrow-down-tray">
                    <span wire:loading.remove wire:target="scrapeLink">Yüklə</span>
                    <span wire:loading wire:target="scrapeLink">...</span>
                </flux:button>
            </div>
            @if($pScrapeError)
            <p class="mt-1 text-xs text-red-500">{{ $pScrapeError }}</p>
            @endif
        </div>

        {{-- Show scraped data preview --}}
        @if($pPrice || $pRooms || $pArea || $pTitle)
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3 text-xs text-green-700 dark:text-green-400 space-y-1">
            <div class="font-semibold">Yükləndi:</div>
            @if($pTitle)<div>{{ $pTitle }}</div>@endif
            <div class="flex flex-wrap gap-x-4 gap-y-0.5">
                @if($pPrice)<span>{{ number_format($pPrice) }} {{ $pCurrency }}</span>@endif
                @if($pRooms)<span>{{ $pRooms }} otaq</span>@endif
                @if($pArea)<span>{{ $pArea }} m²</span>@endif
                @if($pFloor)<span>{{ $pFloor }}{{ $pFloorTotal ? '/'.$pFloorTotal : '' }} mərtəbə</span>@endif
            </div>
        </div>
        @endif

        {{-- Notes --}}
        <flux:textarea wire:model="pNotes" label="Mülk haqqında məlumat" rows="4"
            placeholder="Əlavə məlumat, qeydlər..." />

        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showPropertyForm', false)" variant="ghost">Ləğv et</flux:button>
            <flux:button type="submit" variant="primary">Saxla</flux:button>
        </div>
    </form>
</flux:modal>

@endif
</div>

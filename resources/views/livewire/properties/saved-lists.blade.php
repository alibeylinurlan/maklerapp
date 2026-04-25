<?php

use App\Models\SavedList;
use App\Models\SavedListItem;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;

new class extends Component {
    #[Url(as: 'listid')]
    public ?int $activeList = null;

    public int $listLimit = 20;

    public string $newListName = '';
    public bool $showCreateModal = false;
    public ?int $renamingList = null;
    public string $renameValue = '';

    public function loadMoreLists(): void
    {
        usleep(600000); // 0.6s gecikmə
        $this->listLimit += 20;
    }

    public function mount(): void
    {
        if ($this->activeList) {
            // URL-dən gələn listid yalnız bu userin siyahısıdırsa qəbul et
            $valid = SavedList::where('id', $this->activeList)
                ->where('user_id', auth()->id())
                ->exists();
            if (!$valid) {
                $this->activeList = null;
            }
        }

        if (!$this->activeList) {
            $first = SavedList::where('user_id', auth()->id())->orderByDesc('last_activity_at')->first();
            $this->activeList = $first?->id;
        }
    }

    public function selectList(int $id): void
    {
        // Yalnız bu userin siyahısına keçid icazəsi var
        $owns = SavedList::where('id', $id)->where('user_id', auth()->id())->exists();
        if ($owns) {
            $this->activeList = $id;
        }
    }

    public function createList(): void
    {
        $this->validate(['newListName' => 'required|min:1|max:100']);

        $list = SavedList::create([
            'user_id'          => auth()->id(),
            'name'             => $this->newListName,
            'last_activity_at' => now(),
        ]);

        $this->activeList = $list->id;
        $this->newListName = '';
        $this->showCreateModal = false;
    }

    public function startRename(int $id, string $currentName): void
    {
        $this->renamingList = $id;
        $this->renameValue = $currentName;
    }

    public function saveRename(): void
    {
        $this->validate(['renameValue' => 'required|min:1|max:100']);

        $list = SavedList::where('id', $this->renamingList)->where('user_id', auth()->id())->first();
        if ($list) {
            $list->name = $this->renameValue;
            $list->last_activity_at = now();
            $list->save();
        }

        $this->renamingList = null;
        $this->renameValue = '';
    }

    public function deleteList(int $id): void
    {
        SavedList::where('id', $id)->where('user_id', auth()->id())->delete();

        if ($this->activeList === $id) {
            $first = SavedList::where('user_id', auth()->id())->orderByDesc('last_activity_at')->first();
            $this->activeList = $first?->id;
        }
    }

    public function removeProperty(int $itemId): void
    {
        $item = SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
            ->where('id', $itemId)
            ->first();

        if ($item) {
            $listId = $item->saved_list_id;
            $item->delete();
            SavedList::where('id', $listId)->update(['last_activity_at' => now()]);
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $canAccess = $user->hasAnyRole(['superadmin', 'admin', 'developer']) || $user->hasFeature('saved_lists');
        if (!$canAccess) {
            return ['canAccess' => false, 'lists' => collect(), 'hasMoreLists' => false, 'properties' => collect()];
        }

        $allLists = SavedList::where('user_id', auth()->id())
            ->withCount('items')
            ->orderByDesc('last_activity_at')
            ->limit($this->listLimit + 1)
            ->get();

        $hasMoreLists = $allLists->count() > $this->listLimit;
        $lists = $allLists->take($this->listLimit);

        $properties = collect();
        if ($this->activeList) {
            $properties = SavedListItem::with('property.category')
                ->whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
                ->where('saved_list_id', $this->activeList)
                ->orderByDesc('created_at')
                ->get();
        }

        return compact('lists', 'hasMoreLists', 'properties', 'canAccess');
    }
}; ?>

<div>
@if(!$canAccess)
    @include('livewire.partials.plan-gate', ['planKey' => 'saved_lists', 'planName' => 'Saxlanılanlar', 'pageTitle' => 'Saxlanılanlar'])
@else
<div class="mx-auto max-w-[1600px]">
    <div class="flex gap-4 items-start">

    {{-- LEFT: main content --}}
    <div class="flex-1 min-w-0" style="margin-right: 18rem;">

    <div class="flex gap-5 items-start">
        {{-- Sidebar: list of lists --}}
        <div class="shrink-0 flex flex-col rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden"
             style="position: fixed; top: 1rem; bottom: 1rem; width: 16rem; box-shadow: inset 0 2px 8px 0 rgba(0,0,0,0.06), 0 4px 24px 0 rgba(0,0,0,0.08);">

            {{-- Header --}}
            <div class="shrink-0 px-4 pt-4 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Saxlanılanlar</span>
                    <button wire:click="$set('showCreateModal', true)"
                            class="flex items-center gap-1 rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white px-2.5 py-1 text-xs font-medium transition-colors">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Yeni
                    </button>
                </div>
            </div>

            {{-- Scrollable list --}}
            <div class="flex-1 overflow-y-auto px-2 py-2 space-y-0.5" x-data
                 x-on:scroll.passive="if($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 40) $wire.loadMoreLists()">

                @forelse($lists as $list)
                    @if($renamingList === $list->id)
                        <div class="flex gap-1 px-1 py-1" x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                            <input wire:model="renameValue"
                                   wire:keydown.enter="saveRename"
                                   wire:keydown.escape="$set('renamingList', null)"
                                   type="text"
                                   maxlength="100"
                                   class="flex-1 min-w-0 rounded-lg border border-indigo-300 dark:border-indigo-600 bg-zinc-50 dark:bg-zinc-800 px-2 py-1.5 text-sm text-zinc-800 dark:text-zinc-200 outline-none focus:ring-1 focus:ring-indigo-300" />
                            <button wire:click="saveRename" class="rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white px-2.5 py-1.5 text-xs font-medium transition-colors">✓</button>
                        </div>
                    @else
                        <div class="group flex items-center gap-2 rounded-xl px-3 py-2.5 cursor-pointer transition-all
                                    {{ $activeList === $list->id
                                        ? 'bg-indigo-500 shadow-md shadow-indigo-200 dark:shadow-indigo-900/40'
                                        : 'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                             wire:click="selectList({{ $list->id }})">
                            <svg class="size-4 shrink-0 {{ $activeList === $list->id ? 'text-white' : 'text-zinc-400' }}"
                                 fill="{{ $activeList === $list->id ? 'currentColor' : 'none' }}"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                            </svg>
                            <span class="flex-1 text-sm font-medium truncate {{ $activeList === $list->id ? 'text-white' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $list->name }}</span>
                            <span class="text-xs shrink-0 rounded-full px-1.5 py-0.5 {{ $activeList === $list->id ? 'bg-white/20 text-white' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400' }}">{{ $list->items_count }}</span>
                            <div class="hidden group-hover:flex items-center gap-0.5 shrink-0">
                                <button wire:click.stop="startRename({{ $list->id }}, '{{ addslashes($list->name) }}')"
                                        class="p-0.5 rounded transition-colors {{ $activeList === $list->id ? 'text-white/70 hover:text-white' : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' }}"
                                        title="Adını dəyiş">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button wire:click.stop="deleteList({{ $list->id }})"
                                        wire:confirm="Bu siyahını silmək istədiyinizə əminsiniz?"
                                        class="p-0.5 rounded transition-colors {{ $activeList === $list->id ? 'text-white/70 hover:text-red-200' : 'text-zinc-400 hover:text-red-500' }}"
                                        title="Sil">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="py-8 text-center text-sm text-zinc-400">Hələ siyahı yoxdur</div>
                @endforelse

                @if($hasMoreLists)
                    <div wire:loading.remove wire:target="loadMoreLists" class="py-2 text-center text-xs text-zinc-400">
                        Daha çox yükləmək üçün aşağı sürüşdürün
                    </div>
                    <div wire:loading wire:target="loadMoreLists" class="py-2 w-full flex items-center justify-center gap-1.5">
                        <span class="text-xs font-medium" style="color: #71717a;">Yüklənir...</span>
                    </div>
                @endif

            </div>{{-- end scrollable list --}}
        </div>

        {{-- Create list modal --}}
        @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.set('showCreateModal', false)">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" wire:click="$set('showCreateModal', false)"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 p-6"
                 x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-100 mb-4">Yeni siyahı yarat</h3>
                <input wire:model="newListName"
                       wire:keydown.enter="createList"
                       type="text"
                       placeholder="Siyahı adı..."
                       maxlength="100"
                       class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-800 transition-all" />
                @error('newListName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showCreateModal', false)"
                            class="rounded-xl px-4 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        Ləğv et
                    </button>
                    <button wire:click="createList"
                            class="rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 text-sm font-medium transition-colors">
                        Yarat
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- Cards --}}
        <div class="flex-1 min-w-0 relative" style="margin-left: 17rem;">

            <div wire:loading wire:target="selectList"
                 style="position:absolute;inset:0;z-index:20">
                <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:8px">
                    <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0s"></span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.4s"></span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#a1a1aa;animation:softBlink 1.2s ease-in-out infinite 0.8s"></span>
                </div>
            </div>
            <div wire:loading.remove wire:target="selectList">
            @if($lists->isEmpty())
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <svg class="size-12 text-zinc-300 dark:text-zinc-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                    </svg>
                    <p class="text-zinc-500 dark:text-zinc-400 font-medium">Hələ siyahı yoxdur</p>
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">Elanlar səhifəsindən bookmark düyməsinə basın</p>
                </div>
            @elseif($properties->isEmpty())
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <svg class="size-12 text-zinc-300 dark:text-zinc-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <p class="text-zinc-500 dark:text-zinc-400 font-medium">Bu siyahı boşdur</p>
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">Elanlar səhifəsindən bookmark ilə bu siyahıya əlavə edin</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach($properties as $item)
                        @php
                            $p = $item->property;
                            if (!$p) continue;
                            $thumb = null;
                            if (!empty($p->photos)) {
                                $thumb = $p->photos[0]['medium'] ?? $p->photos[0]['thumb'] ?? $p->photos[0]['large'] ?? null;
                            }
                        @endphp
                        <div class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md hover:-translate-y-0.5 dark:border-zinc-700 dark:bg-zinc-800">
                            {{-- Photo --}}
                            <a href="{{ $p->full_url }}" target="_blank" rel="noopener" class="relative block aspect-[4/3] overflow-hidden bg-zinc-200 dark:bg-zinc-700">
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-zinc-400">
                                        <flux:icon.photo class="size-10 opacity-40" />
                                    </div>
                                @endif

                                {{-- Category badge --}}
                                @if($p->category)
                                <div class="absolute top-2 left-2 rounded-md bg-black/50 px-1.5 py-0.5 text-xs text-white">
                                    {{ $p->category->name_az }}
                                </div>
                                @endif

                                {{-- Download button --}}
                                <button
                                    onclick="event.preventDefault(); event.stopPropagation(); window.location='{{ route('properties.image-download', $p->id) }}'"
                                    class="absolute bottom-2 right-[4.5rem] flex items-center justify-center size-7 rounded-full bg-black/50 hover:bg-white/20 text-white backdrop-blur-sm transition-all"
                                    title="Şəkilləri yüklə">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                    </svg>
                                </button>
                                {{-- Info button --}}
                                <button
                                    onclick="event.preventDefault(); event.stopPropagation(); window.location='{{ route('properties.show', $p->id) }}'"
                                    class="absolute bottom-2 right-10 flex items-center justify-center size-7 rounded-full bg-black/50 hover:bg-white/20 text-white backdrop-blur-sm transition-all"
                                    title="Ətraflı bax">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                                    </svg>
                                </button>
                                {{-- Remove button --}}
                                <button wire:click="removeProperty({{ $item->id }})"
                                        wire:confirm="Bu elanı siyahıdan çıxarmaq istədiyinizə əminsiniz?"
                                        onclick="event.preventDefault(); event.stopPropagation();"
                                        class="absolute bottom-2 right-2 flex items-center justify-center size-7 rounded-full bg-indigo-500 hover:bg-red-500 text-white backdrop-blur-sm transition-all"
                                        title="Siyahıdan çıxar">
                                    <svg class="size-4" fill="currentColor" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                    </svg>
                                </button>
                            </a>

                            {{-- Info --}}
                            <a href="{{ $p->full_url }}" target="_blank" rel="noopener" class="flex flex-1 flex-col gap-1 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="text-base font-bold text-zinc-900 dark:text-white">
                                    @if($p->price)
                                        {{ number_format($p->price) }} {{ $p->currency === 'azn' ? '₼' : ($p->currency === 'usd' ? '$' : $p->currency) }}
                                    @else
                                        <span class="text-zinc-400 font-normal text-sm">Qiymət yox</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-x-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                                    @if($p->rooms)
                                        <span>{{ $p->rooms }} otaqlı</span>
                                    @endif
                                    @if($p->rooms && $p->area)
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                    @endif
                                    @if($p->area)
                                        <span>{{ $p->area }} m²</span>
                                    @endif
                                    @if(($p->rooms || $p->area) && $p->floor)
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                    @endif
                                    @if($p->floor)
                                        <span>{{ $p->floor }}{{ $p->floor_total ? '/'.$p->floor_total : '' }} mərtəbə</span>
                                    @endif
                                </div>

                                @if($p->location_full_name)
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $p->location_full_name }}
                                </div>
                                @endif

                                <div class="mt-auto pt-1 text-xs text-zinc-400">
                                    {{ $p->bumped_at?->format('d.m.Y H:i') }}
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>{{-- end cards inner --}}
        </div>{{-- end wire:loading.remove --}}
        </div>{{-- end cards wrapper --}}
    </div>

    </div>{{-- end LEFT --}}

    {{-- RIGHT: live feed panel --}}
    <div class="w-72 shrink-0 rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
         style="background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);
                position: fixed; top: 1rem; right: 1rem; bottom: 1rem;">
        <x-live-feed />
    </div>

    </div>{{-- end two-column --}}

@livewire('properties.save-modal', key('save-modal-saved'))
</div>

<style>
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes softBlink {
    0%, 100% { opacity: 0.15; }
    50% { opacity: 0.9; }
}
</style>
@endif

<?php

use App\Models\Property;
use App\Models\PropertyNote;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public Property $property;
    public int $id;
    public string $noteBody = '';
    public ?int $editingNoteId = null;

    public function mount(int $id): void
    {
        if (!user_has_feature('properties_view')) {
            abort(403);
        }
        $this->id = $id;
        $this->property = Property::with(['category', 'priceHistory' => fn($q) => $q->take(6)])->findOrFail($id);
    }

    public function isSaved(): bool
    {
        return \App\Models\SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
            ->where('property_id', $this->id)
            ->exists();
    }

    public function saveNote(): void
    {
        if (!user_has_feature('notes')) return;
        $body = trim($this->noteBody);
        if (!$body) return;

        if ($this->editingNoteId) {
            PropertyNote::where('id', $this->editingNoteId)->where('user_id', auth()->id())->update(['body' => $body]);
            $this->editingNoteId = null;
        } else {
            PropertyNote::create([
                'property_id' => $this->id,
                'user_id' => auth()->id(),
                'body' => $body,
            ]);
        }
        $this->noteBody = '';
    }

    public function editNote(int $noteId): void
    {
        if (!user_has_feature('notes')) return;
        $note = PropertyNote::where('id', $noteId)->where('user_id', auth()->id())->firstOrFail();
        $this->editingNoteId = $noteId;
        $this->noteBody = $note->body;
    }

    public function deleteNote(int $noteId): void
    {
        if (!user_has_feature('notes')) return;
        PropertyNote::where('id', $noteId)->where('user_id', auth()->id())->delete();
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId = null;
        $this->noteBody = '';
    }

    public function with(): array
    {
        $notes = user_has_feature('notes')
            ? PropertyNote::where('property_id', $this->id)
                ->where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->get()
            : collect();
        return ['notes' => $notes];
    }
}; ?>

@php
    $thumb = null;
    if (!empty($property->photos)) {
        $first = $property->photos[0];
        $thumb = $first['medium'] ?? $first['large'] ?? $first['thumb'] ?? null;
    }
    $curr = $property->currency === 'azn' ? '₼' : ($property->currency === 'usd' ? '$' : $property->currency);
@endphp

<div>
<div class="flex gap-4 items-start">

{{-- MAIN CONTENT --}}
<div class="flex-1 min-w-0" style="margin-right: 19rem;">
<div class="flex flex-col lg:flex-row gap-6 items-start">

    {{-- LEFT: photo + info --}}
    <div class="w-full lg:w-auto lg:shrink-0 space-y-4">

        {{-- Photo --}}
        <div class="relative rounded-2xl overflow-hidden bg-zinc-100 dark:bg-zinc-800" style="width:280px; max-width:100%; aspect-ratio:4/3;">
            @if($thumb)
                <img src="{{ $thumb }}" class="w-full h-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <svg class="size-10 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
                </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex flex-col justify-end p-3">
                <div class="flex items-end justify-between gap-2">
                    <div class="min-w-0">
                        @if($property->category)
                            <div class="text-[9px] font-semibold uppercase tracking-widest text-white/60 mb-0.5">{{ $property->category->name_az }}</div>
                        @endif
                        <div class="text-sm font-semibold text-white leading-tight truncate">{{ $property->location_full_name ?? '—' }}</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            x-data="{ saved: {{ $this->isSaved() ? 'true' : 'false' }} }"
                            x-on:bookmark-changed.window="if ($event.detail.propertyId == {{ $property->id }}) saved = $event.detail.isSaved"
                            onclick="Livewire.dispatch('save-property', { propertyId: {{ $property->id }} })"
                            :class="saved ? 'bg-indigo-500 hover:bg-indigo-600' : 'bg-white/15 hover:bg-white/30'"
                            class="size-7 flex items-center justify-center rounded-full text-white transition-colors backdrop-blur-sm"
                            title="Siyahıya əlavə et">
                            <svg class="size-3.5" :fill="saved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                            </svg>
                        </button>
                        <a href="{{ $property->full_url }}" target="_blank"
                           class="size-7 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/30 text-white transition-colors backdrop-blur-sm">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Links --}}
        <div class="flex items-center gap-3" style="width:280px; max-width:100%;">
            <a href="{{ $property->full_url }}" target="_blank"
               class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-indigo-500 transition-colors">
                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                Daha ətraflı
            </a>
            @if(!empty($property->photos))
                <a href="{{ route('properties.image-download', $id) }}"
                   class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-indigo-500 transition-colors">
                    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Şəkilləri yüklə
                </a>
            @endif
        </div>
    </div>

    {{-- CENTER: details --}}
    <div class="flex-1 min-w-0 space-y-5">

        {{-- Price --}}
        <div>
            <div class="text-3xl font-extrabold text-zinc-900 dark:text-white tracking-tight">
                @if($property->price)
                    {{ number_format($property->price) }} <span class="text-xl font-semibold text-zinc-400">{{ $curr }}</span>
                @else
                    <span class="text-zinc-400 font-normal text-xl">Qiymət yox</span>
                @endif
            </div>
            <div class="flex items-center gap-2 mt-1 text-xs text-zinc-400">
                @if($property->bumped_at)<span>Yeniləndi: {{ $property->bumped_at->format('d.m.Y H:i') }}</span>@endif
                @if($property->bumped_at && $property->first_seen_at)<span class="text-zinc-300 dark:text-zinc-700">·</span>@endif
                @if($property->first_seen_at)<span>İlk: {{ $property->first_seen_at->format('d.m.Y') }}</span>@endif
            </div>
        </div>

        {{-- Specs row --}}
        <div class="flex flex-wrap gap-x-6 gap-y-3">
            @if($property->rooms)
            <div>
                <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-0.5">Otaq</div>
                <div class="text-base font-bold text-zinc-800 dark:text-zinc-100">{{ $property->rooms }}</div>
            </div>
            @endif
            @if($property->area)
            <div>
                <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-0.5">Sahə</div>
                <div class="text-base font-bold text-zinc-800 dark:text-zinc-100">{{ $property->area }} m²</div>
            </div>
            @endif
            @if($property->floor)
            <div>
                <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-0.5">Mərtəbə</div>
                <div class="text-base font-bold text-zinc-800 dark:text-zinc-100">{{ $property->floor }}{{ $property->floor_total ? '/'.$property->floor_total : '' }}</div>
            </div>
            @endif
            @if($property->location_full_name)
            <div>
                <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-0.5">Ərazi</div>
                <div class="text-base font-bold text-zinc-800 dark:text-zinc-100">{{ $property->location_full_name }}</div>
            </div>
            @endif
        </div>

        {{-- Attribute tags --}}
        @php $attrs = array_filter(['İpoteka' => $property->has_mortgage, 'Çıxarış' => $property->has_bill_of_sale, 'Təmirli' => $property->has_repair, 'Kirayə' => $property->is_leased]); @endphp
        @if(count($attrs))
        <div class="flex flex-wrap gap-1.5">
            @foreach($attrs as $label => $val)
            <span class="px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-xs font-medium text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">{{ $label }}</span>
            @endforeach
        </div>
        @endif

        {{-- Price history --}}
        <div>
            <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-2">Qiymət tarixi</div>
            @if(user_has_feature('price_history'))
                @if($property->priceHistory->count() > 0)
                <div class="space-y-1.5">
                    @foreach($property->priceHistory as $h)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-400">{{ \Carbon\Carbon::parse($h->recorded_at)->format('d.m.Y') }}</span>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format($h->price) }} {{ $h->currency === 'AZN' ? '₼' : '$' }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-zinc-400">Bu elan üçün qiymət dəyişikliyi yoxdur.</p>
                @endif
            @else
            <div class="flex items-center gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                <svg class="size-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <div>
                    <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Ekspert tarifi tələb olunur</p>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500">Qiymət tarixçəsini görmək üçün tarifinizi yükseldin</p>
                </div>
            </div>
            @endif
        </div>

    </div>{{-- end center --}}

    {{-- RIGHT: notes sticky --}}
    <div class="w-full lg:w-80 shrink-0" style="position: sticky; top: 1rem;">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
            <div class="text-[10px] uppercase tracking-wider text-zinc-400 mb-3">Qeydlər <span class="normal-case tracking-normal text-zinc-300 dark:text-zinc-600">— yalnız siz görə bilərsiniz</span></div>
            @if(user_has_feature('notes'))
                <textarea
                    wire:model="noteBody"
                    rows="4"
                    placeholder="{{ $editingNoteId ? 'Redaktə...' : 'Qeyd əlavə et...' }}"
                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm text-zinc-800 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 resize-none mb-2"
                ></textarea>
                <div class="flex gap-2 mb-4">
                    <button wire:click="saveNote" class="flex-1 rounded-xl bg-indigo-600 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-colors">
                        {{ $editingNoteId ? 'Yenilə' : 'Saxla' }}
                    </button>
                    @if($editingNoteId)
                    <button wire:click="cancelEdit" class="rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">Ləğv</button>
                    @endif
                </div>
                @forelse($notes as $note)
                <div class="group border-t border-zinc-100 dark:border-zinc-800 pt-3 pb-1 first:border-t-0 first:pt-0">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap leading-relaxed">{{ $note->body }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[10px] text-zinc-400">{{ $note->created_at->diffForHumans() }}</span>
                        <div class="flex gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="editNote({{ $note->id }})" class="text-[11px] text-zinc-400 hover:text-indigo-500">Redaktə</button>
                            <button wire:click="deleteNote({{ $note->id }})" wire:confirm="Silinsin?" class="text-[11px] text-zinc-400 hover:text-red-500">Sil</button>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-xs text-zinc-400 text-center py-2">Hələ qeyd yoxdur</p>
                @endforelse
            @else
            <div class="flex items-center gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2.5">
                <svg class="size-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <div>
                    <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Peşəkar tarifi tələb olunur</p>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500">Qeyd əlavə etmək üçün tarifinizi yükseldin</p>
                </div>
            </div>
            @endif
        </div>
    </div>

</div>{{-- end flex --}}
</div>{{-- end main content --}}

{{-- LIVE FEED: fixed right --}}
<div class="w-72 shrink-0 rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
     style="background: linear-gradient(160deg, #1e1b4b 0%, #0f172a 60%, #064e3b 100%);
            position: fixed; top: 1rem; right: 1rem; bottom: 1rem;">
    <x-live-feed />
</div>

</div>{{-- end outer flex --}}
@livewire('properties.save-modal', key('save-modal-show'))
</div>

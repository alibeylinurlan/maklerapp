<?php

use App\Models\Property;
use App\Models\PropertyNote;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $propertyId = null;
    public ?Property $property = null;
    public string $noteBody = '';
    public ?int $editingNoteId = null;

    #[On('loadPropertyPanel')]
    public function load(int $id): void
    {
        $this->propertyId = $id;
        $this->property = Property::with(['category', 'notes' => fn($q) => $q->where('user_id', auth()->id())->orderByDesc('created_at')])->find($id);
        $this->noteBody = '';
        $this->editingNoteId = null;
        $this->dispatch('property-panel-opened');
    }

    public function close(): void
    {
        $this->propertyId = null;
        $this->property = null;
        $this->noteBody = '';
        $this->editingNoteId = null;
        $this->dispatch('property-panel-closed');
    }

    public function saveNote(): void
    {
        $body = trim($this->noteBody);
        if (!$body) return;

        if ($this->editingNoteId) {
            PropertyNote::where('id', $this->editingNoteId)->where('user_id', auth()->id())->update(['body' => $body]);
            $this->editingNoteId = null;
        } else {
            PropertyNote::create([
                'property_id' => $this->propertyId,
                'user_id' => auth()->id(),
                'body' => $body,
            ]);
        }

        $this->noteBody = '';
        $this->refreshNotes();
    }

    public function editNote(int $id): void
    {
        $note = PropertyNote::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->editingNoteId = $id;
        $this->noteBody = $note->body;
    }

    public function deleteNote(int $id): void
    {
        PropertyNote::where('id', $id)->where('user_id', auth()->id())->delete();
        $this->refreshNotes();
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId = null;
        $this->noteBody = '';
    }

    private function refreshNotes(): void
    {
        if ($this->property) {
            $this->property->setRelation('notes',
                PropertyNote::where('property_id', $this->propertyId)
                    ->where('user_id', auth()->id())
                    ->orderByDesc('created_at')
                    ->get()
            );
        }
    }

}; ?>

<div class="flex flex-col h-full overflow-hidden">
@if($property)

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 shrink-0">
        <div class="flex items-center gap-2 min-w-0">
            @if($property->category)
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 shrink-0">
                    {{ $property->category->name_az }}
                </span>
            @endif
            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 truncate">
                {{ $property->location_full_name ?? 'Elan məlumatı' }}
            </span>
        </div>
        <div class="flex items-center gap-1 shrink-0 ml-2">
            <a href="{{ $property->full_url }}" target="_blank"
               class="flex items-center justify-center size-7 rounded-lg text-zinc-400 hover:text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
               title="Bina.az-da aç">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
            <button wire:click="close"
                    class="flex items-center justify-center size-7 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    title="Bağla">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">

        {{-- Photos --}}
        @if(!empty($property->photos))
        <div class="relative overflow-hidden"
             x-data="{ photoIdx: 0, photos: {{ json_encode(array_map(fn($p) => $p['large'] ?? $p['medium'] ?? $p['thumb'] ?? null, $property->photos)) }} }"
             style="height: 200px;">
            <template x-for="(src, i) in photos" :key="i">
                <img :src="src"
                     x-show="photoIdx === i"
                     x-transition:enter="transition-opacity duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="absolute inset-0 w-full h-full object-cover">
            </template>
            @if(count($property->photos) > 1)
            <button @click="photoIdx = (photoIdx - 1 + photos.length) % photos.length"
                    class="absolute left-2 top-1/2 -translate-y-1/2 size-7 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </button>
            <button @click="photoIdx = (photoIdx + 1) % photos.length"
                    class="absolute right-2 top-1/2 -translate-y-1/2 size-7 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </button>
            <div class="absolute bottom-2 right-2 rounded-full bg-black/50 px-2 py-0.5 text-[10px] text-white"
                 x-text="(photoIdx + 1) + ' / ' + photos.length"></div>
            @endif
        </div>
        @endif

        <div class="px-4 pt-4 pb-3 space-y-4">

            {{-- Price --}}
            <div>
                <div class="text-2xl font-extrabold text-zinc-900 dark:text-white">
                    @if($property->price)
                        {{ number_format($property->price) }}
                        {{ $property->currency === 'azn' ? '₼' : ($property->currency === 'usd' ? '$' : $property->currency) }}
                    @else
                        <span class="text-zinc-400 font-normal text-base">Qiymət yox</span>
                    @endif
                </div>
                @if($property->bumped_at)
                <div class="text-xs text-zinc-400 mt-0.5">{{ $property->bumped_at->format('d.m.Y H:i') }}</div>
                @endif
            </div>

            {{-- Specs grid --}}
            <div class="grid grid-cols-2 gap-2">
                @if($property->rooms)
                <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                    <svg class="size-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                    <div>
                        <div class="text-[10px] text-zinc-400">Otaq</div>
                        <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $property->rooms }}</div>
                    </div>
                </div>
                @endif

                @if($property->area)
                <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                    <svg class="size-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                    <div>
                        <div class="text-[10px] text-zinc-400">Sahə</div>
                        <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $property->area }} m²</div>
                    </div>
                </div>
                @endif

                @if($property->floor)
                <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                    <svg class="size-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/></svg>
                    <div>
                        <div class="text-[10px] text-zinc-400">Mərtəbə</div>
                        <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $property->floor }}{{ $property->floor_total ? '/'.$property->floor_total : '' }}</div>
                    </div>
                </div>
                @endif

                @if($property->location_full_name)
                <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                    <svg class="size-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    <div class="min-w-0">
                        <div class="text-[10px] text-zinc-400">Ərazi</div>
                        <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 truncate">{{ $property->location_full_name }}</div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Badges --}}
            <div class="flex flex-wrap gap-1.5">
                @if($property->has_mortgage)
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        İpoteka
                    </span>
                @endif
                @if($property->has_bill_of_sale)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/30 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-300">
                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Çıxarış var
                    </span>
                @endif
                @if($property->has_repair)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/30 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/></svg>
                        Təmirli
                    </span>
                @endif
                @if($property->is_vipped)
                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 dark:bg-violet-900/30 px-2.5 py-1 text-xs font-medium text-violet-700 dark:text-violet-300">VIP</span>
                @endif
            </div>

            {{-- Price history --}}
            @php $history = $property->priceHistory()->take(5)->get(); @endphp
            @if($history->count() > 0)
            <div>
                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-2">Qiymət tarixi</div>
                <div class="space-y-1">
                    @foreach($history as $h)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">{{ $h->recorded_at?->format('d.m.Y') }}</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">
                            {{ number_format($h->price) }} {{ $h->currency === 'azn' ? '₼' : '$' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Notes --}}
            <div>
                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-2">Qeydlər</div>

                {{-- Note form --}}
                <div class="space-y-2 mb-3">
                    <textarea
                        wire:model="noteBody"
                        rows="2"
                        placeholder="{{ $editingNoteId ? 'Qeydi redaktə edin...' : 'Yeni qeyd əlavə edin...' }}"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-800 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 resize-none"
                    ></textarea>
                    <div class="flex gap-2">
                        <button wire:click="saveNote"
                                class="flex-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors">
                            {{ $editingNoteId ? 'Yenilə' : 'Əlavə et' }}
                        </button>
                        @if($editingNoteId)
                        <button wire:click="cancelEdit"
                                class="rounded-lg bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                            Ləğv et
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Note list --}}
                @forelse($property->notes as $note)
                <div class="group relative rounded-lg border border-zinc-100 dark:border-zinc-700/50 bg-zinc-50 dark:bg-zinc-800/40 px-3 py-2 mb-2">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">{{ $note->body }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[10px] text-zinc-400">{{ $note->created_at->diffForHumans() }}</span>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="editNote({{ $note->id }})"
                                    class="text-[10px] text-zinc-400 hover:text-indigo-500 transition-colors px-1">
                                Redaktə
                            </button>
                            <button wire:click="deleteNote({{ $note->id }})"
                                    wire:confirm="Bu qeydi silmək istəyirsiniz?"
                                    class="text-[10px] text-zinc-400 hover:text-red-500 transition-colors px-1">
                                Sil
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-xs text-zinc-400 text-center py-3">Hələ qeyd yoxdur</div>
                @endforelse
            </div>

        </div>
    </div>
@else
<div class="flex items-center justify-center h-full text-zinc-400 text-sm">
    Elan seçin
</div>
@endif
</div>

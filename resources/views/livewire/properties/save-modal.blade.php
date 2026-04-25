<?php

use App\Models\SavedList;
use App\Models\SavedListItem;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public bool $show = false;
    public ?int $propertyId = null;
    public string $newListName = '';
    public bool $showNewForm = false;
    public array $savedInLists = [];

    #[On('save-property')]
    public function open(int $propertyId): void
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['superadmin', 'admin', 'developer']) && !$user->hasFeature('saved_lists')) {
            return;
        }
        $this->propertyId = $propertyId;
        $this->newListName = '';
        $this->showNewForm = false;
        $this->loadSavedState();
        $this->show = true;
    }

    private function loadSavedState(): void
    {
        $this->savedInLists = SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
            ->where('property_id', $this->propertyId)
            ->pluck('saved_list_id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    public function toggleList(int $listId): void
    {
        $item = SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', auth()->id()))
            ->where('saved_list_id', $listId)
            ->where('property_id', $this->propertyId)
            ->first();

        if ($item) {
            $item->delete();
            $this->savedInLists = array_values(array_filter($this->savedInLists, fn($id) => $id != $listId));
        } else {
            SavedListItem::create([
                'saved_list_id' => $listId,
                'property_id'   => $this->propertyId,
            ]);
            $this->savedInLists[] = (string) $listId;
        }

        \App\Models\SavedList::where('id', $listId)->update(['last_activity_at' => now()]);

        // Bookmark düyməsini anlıq yenilə
        $isSaved = !empty($this->savedInLists);
        $this->dispatch('bookmark-changed', propertyId: $this->propertyId, isSaved: $isSaved);
        $this->dispatch('save-modal-close');
    }

    public function createList(): void
    {
        $this->validate(['newListName' => 'required|min:1|max:100']);

        $list = SavedList::create([
            'user_id'          => auth()->id(),
            'name'             => $this->newListName,
            'last_activity_at' => now(),
        ]);

        SavedListItem::create([
            'saved_list_id' => $list->id,
            'property_id'   => $this->propertyId,
        ]);

        $this->savedInLists[] = (string) $list->id;
        $this->newListName = '';
        $this->showNewForm = false;

        $this->dispatch('bookmark-changed', propertyId: $this->propertyId, isSaved: true);
        $this->dispatch('save-modal-close');
    }

    public function with(): array
    {
        return [
            'lists' => SavedList::where('user_id', auth()->id())
                ->withCount('items')
                ->orderByDesc('updated_at')
                ->get(),
        ];
    }
}; ?>

<div x-data="{ open: false }"
     @save-property.window="open = true"
     @save-modal-close.window="open = false"
     @keydown.escape.window="open = false">
<div class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     x-show="open"
     x-cloak
     style="display:none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
         @click="open = false"></div>

    {{-- Modal --}}
    <div class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <svg class="size-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                </svg>
                <span class="font-semibold text-zinc-800 dark:text-zinc-100">Siyahıya əlavə et</span>
            </div>
            <button @click="open = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- List --}}
        <div class="max-h-72 overflow-y-auto px-4 py-3 space-y-1.5">
            @forelse($lists as $list)
            @php $isSaved = in_array((string)$list->id, $savedInLists); @endphp
            <button wire:click="toggleList({{ $list->id }})"
                    class="w-full flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors
                           {{ $isSaved ? 'bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700' : 'bg-zinc-50 dark:bg-zinc-800 border border-transparent hover:border-zinc-200 dark:hover:border-zinc-700' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <svg class="size-4 shrink-0 {{ $isSaved ? 'text-indigo-500' : 'text-zinc-400' }}" fill="{{ $isSaved ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                    </svg>
                    <span class="text-sm font-medium truncate {{ $isSaved ? 'text-indigo-700 dark:text-indigo-300' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $list->name }}</span>
                </div>
                <span class="text-xs text-zinc-400 shrink-0">{{ $list->items_count }}</span>
            </button>
            @empty
            <p class="text-sm text-zinc-400 text-center py-4">Hələ siyahı yoxdur</p>
            @endforelse
        </div>

        {{-- New list --}}
        <div class="px-4 pb-4 pt-2 border-t border-zinc-100 dark:border-zinc-800">
            @if($showNewForm)
            <div class="flex gap-2" x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                <input wire:model="newListName"
                       wire:keydown.enter="createList"
                       wire:keydown.escape="$set('showNewForm', false)"
                       type="text"
                       placeholder="Siyahı adı..."
                       maxlength="100"
                       class="flex-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300 transition-colors" />
                <button wire:click="createList"
                        class="rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 text-sm font-medium transition-colors whitespace-nowrap">
                    Yarat və əlavə et
                </button>
            </div>
            @else
            <button wire:click="$set('showNewForm', true)"
                    class="w-full flex items-center gap-2 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 px-3 py-2.5 text-sm text-zinc-500 dark:text-zinc-400 hover:border-indigo-400 hover:text-indigo-500 transition-colors">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Yeni siyahı yarat
            </button>
            @endif
        </div>
    </div>
</div>
</div>

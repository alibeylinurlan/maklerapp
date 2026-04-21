<?php

use App\Models\Location;
use Livewire\Volt\Component;

new class extends Component {

    public function setParent(int $id, ?int $parentId): void
    {
        if ($parentId && $parentId === $id) return;

        // Circular dependency yoxla
        if ($parentId) {
            $ancestor = $parentId;
            while ($ancestor) {
                if ($ancestor === $id) return; // circular
                $ancestor = Location::find($ancestor)?->parent_id;
            }
        }

        Location::where('id', $id)->update(['parent_id' => $parentId]);
    }

    public function with(): array
    {
        $all = Location::orderBy('name_az')->get();

        $roots = $all->whereNull('parent_id')->values();
        $children = $all->whereNotNull('parent_id')->groupBy('parent_id');

        return compact('roots', 'children', 'all');
    }
};
?>

<div x-data="locationTree()" x-init="init()" class="flex gap-4 h-[calc(100vh-8rem)]">

    {{-- LEFT: Unassigned / search panel --}}
    <div class="w-72 shrink-0 flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">

        <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-2">Bütün ərazilər</div>
            <input type="text"
                   x-model="search"
                   placeholder="Axtar..."
                   class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-1.5 text-sm outline-none focus:border-indigo-400 dark:text-zinc-200" />
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-0.5"
             id="all-locations-list">
            @foreach($all->sortBy('name_az') as $loc)
            <div class="location-item flex items-center gap-2 rounded-lg px-2.5 py-1.5 cursor-grab active:cursor-grabbing hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                 data-id="{{ $loc->id }}"
                 data-name="{{ $loc->name_az }}"
                 data-parent="{{ $loc->parent_id ?? '' }}"
                 x-show="matchesSearch('{{ addslashes($loc->name_az) }}')"
                 draggable="true"
                 @dragstart="dragStart($event, {{ $loc->id }}, '{{ addslashes($loc->name_az) }}')"
                 @dragend="dragEnd($event)">

                <svg class="size-3.5 shrink-0 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>

                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate">{{ $loc->name_az }}</span>

                <template x-if="getParentName({{ $loc->id }})">
                    <span class="ml-auto shrink-0 text-[10px] text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded"
                          x-text="getParentName({{ $loc->id }})"></span>
                </template>
            </div>
            @endforeach
        </div>
    </div>

    {{-- RIGHT: Tree panel --}}
    <div class="flex-1 flex flex-col bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">

        <div class="px-5 py-3 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Ərazilər Hierarchiyası</div>
                <div class="text-xs text-zinc-400 mt-0.5">Sola sürüşdürüb buraxın — üst səviyyəyə qoymaq üçün boş sahəyə, alt səviyyəyə qoymaq üçün ərazinin üzərinə atın</div>
            </div>

            <div x-show="pendingCount > 0"
                 class="flex items-center gap-2">
                <span class="text-xs text-amber-600 dark:text-amber-400" x-text="pendingCount + ' dəyişiklik'"></span>
                <button @click="saveAll()"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1.5 text-sm font-medium transition-colors">
                    Yadda saxla
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4">

            {{-- Drop zone for root level --}}
            <div class="drop-zone-root mb-3 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 p-3 text-center text-xs text-zinc-400 transition-colors"
                 @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50','dark:bg-indigo-900/20')"
                 @dragleave="$el.classList.remove('border-indigo-400','bg-indigo-50','dark:bg-indigo-900/20')"
                 @drop.prevent="dropToRoot($event)">
                Buraya atın → üst səviyyəyə qoyulur (parent yox)
            </div>

            {{-- Tree --}}
            <div class="space-y-1" id="location-tree">
                @foreach($roots as $root)
                @include('livewire.admin.locations.tree-node', ['node' => $root, 'children' => $children, 'depth' => 0])
                @endforeach
            </div>

        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-6 right-6 bg-emerald-500 text-white px-4 py-2 rounded-xl shadow-lg text-sm font-medium z-50"
         x-text="toast"
         style="display:none">
    </div>
</div>

<script>
const LOCATIONS_DATA = @json($all->map(fn($l) => ['id' => $l->id, 'name' => $l->name_az, 'parent_id' => $l->parent_id])->values());

function locationTree() {
    return {
        search: '',
        dragging: null,
        draggingName: '',
        pending: {},   // id => newParentId | null
        toast: null,
        toastTimer: null,

        get pendingCount() {
            return Object.keys(this.pending).length;
        },

        init() {
            // Build parent map from PHP data
            this.parentMap = {};
            LOCATIONS_DATA.forEach(l => {
                this.parentMap[l.id] = l.parent_id;
            });
        },

        matchesSearch(name) {
            if (!this.search) return true;
            return name.toLowerCase().includes(this.search.toLowerCase());
        },

        getParentName(id) {
            const parentId = this.parentMap[id];
            if (!parentId) return null;
            const parent = LOCATIONS_DATA.find(l => l.id === parentId);
            return parent ? parent.name : null;
        },

        dragStart(e, id, name) {
            this.dragging = id;
            this.draggingName = name;
            e.dataTransfer.effectAllowed = 'move';
        },

        dragEnd(e) {
            this.dragging = null;
            this.draggingName = '';
            document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over','border-indigo-400','bg-indigo-50','bg-indigo-900/20'));
        },

        dropToParent(e, parentId) {
            e.preventDefault();
            e.stopPropagation();
            if (!this.dragging || this.dragging === parentId) return;

            // Circular check
            if (this.wouldCreateCycle(this.dragging, parentId)) {
                this.showToast('⚠️ Dairəvi asılılıq yaranar, icazə verilmir');
                return;
            }

            this.parentMap[this.dragging] = parentId;
            this.pending[this.dragging] = parentId;

            e.currentTarget.classList.remove('drag-over','border-indigo-400','bg-indigo-50');

            this.$wire.setParent(this.dragging, parentId).then(() => {
                delete this.pending[this.dragging];
                this.showToast('✓ Saxlandı');
                setTimeout(() => this.$wire.$refresh(), 300);
            });
        },

        dropToRoot(e) {
            if (!this.dragging) return;
            this.parentMap[this.dragging] = null;
            this.pending[this.dragging] = null;

            this.$wire.setParent(this.dragging, null).then(() => {
                delete this.pending[this.dragging];
                this.showToast('✓ Üst səviyyəyə qoyuldu');
                setTimeout(() => this.$wire.$refresh(), 300);
            });
        },

        wouldCreateCycle(dragId, targetId) {
            let current = targetId;
            while (current) {
                if (current === dragId) return true;
                current = this.parentMap[current];
            }
            return false;
        },

        saveAll() {
            const entries = Object.entries(this.pending);
            if (!entries.length) return;

            Promise.all(entries.map(([id, parentId]) =>
                this.$wire.setParent(parseInt(id), parentId)
            )).then(() => {
                this.pending = {};
                this.showToast('✓ Bütün dəyişikliklər saxlandı');
                setTimeout(() => this.$wire.$refresh(), 300);
            });
        },

        showToast(msg) {
            this.toast = msg;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast = null, 2500);
        },
    };
}
</script>

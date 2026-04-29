@php
    $nodeChildren = $children->get($node->id, collect());
    $hasChildren  = $nodeChildren->isNotEmpty();
@endphp

<div class="tree-node relative" data-id="{{ $node->id }}">

    {{-- Node row --}}
    <div class="relative flex items-center group"
         style="padding-left: {{ $depth * 1.5 }}rem">

        {{-- Vertical connector line --}}
        @if($depth > 0)
        <span class="absolute left-0 top-0 bottom-1/2 border-l border-zinc-200 dark:border-zinc-700"
              style="left: {{ ($depth - 1) * 1.5 + 0.6 }}rem"></span>
        @endif

        {{-- Drop target + draggable row --}}
        <div class="flex-1 flex items-center gap-2 rounded-lg mx-1 px-2.5 py-1.5 cursor-grab active:cursor-grabbing
                    hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors border border-transparent"
             draggable="true"
             @dragstart="dragStart($event, {{ $node->id }}, '{{ addslashes($node->name_az) }}')"
             @dragend="dragEnd($event)"
             @dragover.prevent="$el.classList.add('border-indigo-400','bg-indigo-50','dark:bg-indigo-900/20')"
             @dragleave="$el.classList.remove('border-indigo-400','bg-indigo-50','dark:bg-indigo-900/20')"
             @drop.prevent="$el.classList.remove('border-indigo-400','bg-indigo-50','dark:bg-indigo-900/20'); dropToParent($event, {{ $node->id }})">

            {{-- Icon: folder or pin --}}
            @if($hasChildren)
            <svg class="size-3.5 shrink-0 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
            </svg>
            @else
            <svg class="size-3.5 shrink-0 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
            @endif

            {{-- Name --}}
            <span class="text-sm text-zinc-700 dark:text-zinc-300 flex-1 truncate">{{ $node->name_az }}</span>

            {{-- Children badge --}}
            @if($hasChildren)
            <span class="shrink-0 text-[10px] font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-1.5 py-0.5 rounded-full">
                {{ $nodeChildren->count() }}
            </span>
            @endif

            {{-- Drop hint --}}
            <span class="shrink-0 text-[10px] text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity select-none">
                buraya at
            </span>

            {{-- Unlink button (only for children) --}}
            @if($depth > 0)
            <button
                type="button"
                title="Əlaqəni kəs"
                class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity rounded p-0.5 text-zinc-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                @click.stop="parentMap[{{ $node->id }}] = null; $wire.setParent({{ $node->id }}, null).then(() => { $wire.$refresh(); showToast('✓ Əlaqə kəsildi'); })">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
            @endif

            {{-- Drag handle dots --}}
            <svg class="size-3 shrink-0 text-zinc-200 dark:text-zinc-700 group-hover:text-zinc-400 transition-colors" fill="currentColor" viewBox="0 0 10 16">
                <circle cx="3" cy="2" r="1.5"/><circle cx="7" cy="2" r="1.5"/>
                <circle cx="3" cy="8" r="1.5"/><circle cx="7" cy="8" r="1.5"/>
                <circle cx="3" cy="14" r="1.5"/><circle cx="7" cy="14" r="1.5"/>
            </svg>
        </div>
    </div>

    {{-- Vertical continuation line for children --}}
    @if($hasChildren)
    <div class="relative">
        {{-- Vertical line along children --}}
        <span class="absolute top-0 bottom-0 border-l border-zinc-200 dark:border-zinc-700"
              style="left: {{ $depth * 1.5 + 0.6 }}rem"></span>

        <div class="tree-children">
            @foreach($nodeChildren->sortBy('name_az') as $child)
                @include('livewire.admin.locations.tree-node', [
                    'node'     => $child,
                    'children' => $children,
                    'depth'    => $depth + 1,
                ])
            @endforeach
        </div>
    </div>
    @endif
</div>

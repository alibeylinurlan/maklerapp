@php
    $nodeChildren = $children->get($node->id, collect());
@endphp

<div class="tree-node"
     data-id="{{ $node->id }}">

    {{-- Node row --}}
    <div class="flex items-center gap-2 rounded-lg px-2 py-1.5 group cursor-grab active:cursor-grabbing hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
         style="padding-left: {{ 0.5 + $depth * 1.25 }}rem"
         draggable="true"
         @dragstart="dragStart($event, {{ $node->id }}, '{{ addslashes($node->name_az) }}')"
         @dragend="dragEnd($event)"
         @dragover.prevent="$event.currentTarget.parentElement.classList.add('ring-2','ring-indigo-400')"
         @dragleave="$event.currentTarget.parentElement.classList.remove('ring-2','ring-indigo-400')"
         @drop.prevent="dropToParent($event, {{ $node->id }})">

        {{-- Indent line --}}
        @if($depth > 0)
        <span class="shrink-0 text-zinc-200 dark:text-zinc-700 select-none">
            @for($i = 0; $i < $depth; $i++) │ @endfor
        </span>
        @endif

        {{-- Drag handle --}}
        <svg class="size-3.5 shrink-0 text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>

        {{-- Name --}}
        <span class="text-sm text-zinc-700 dark:text-zinc-300 flex-1 truncate">{{ $node->name_az }}</span>

        {{-- Children count --}}
        @if($nodeChildren->isNotEmpty())
        <span class="shrink-0 text-[10px] text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded-full">
            {{ $nodeChildren->count() }}
        </span>
        @endif

        {{-- Drop hint --}}
        <span class="shrink-0 text-[10px] text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">
            buraya at →
        </span>
    </div>

    {{-- Children --}}
    @if($nodeChildren->isNotEmpty())
    <div class="tree-children">
        @foreach($nodeChildren->sortBy('name_az') as $child)
            @include('livewire.admin.locations.tree-node', ['node' => $child, 'children' => $children, 'depth' => $depth + 1])
        @endforeach
    </div>
    @endif
</div>

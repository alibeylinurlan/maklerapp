{{--
    Ortaq filter sahələri. Hər iki yerdə (@include ilə) istifadə olunur:
      - resources/views/livewire/properties/index.blade.php
      - resources/views/livewire/customers/index.blade.php (request formu)

    Qəbul olunan dəyişənlər:
      $categories  — Category collection
      $locations   — Location collection
      $f           — field adları massivi, məs:
        [
          'category'      => 'categoryId',
          'locationIds'   => 'locationIds',
          'priceMin'      => 'priceMin',
          'priceMax'      => 'priceMax',
          'roomMin'       => 'roomMin',
          'roomMax'       => 'roomMax',
          'floorMin'      => 'floorMin',
          'floorMax'      => 'floorMax',
          'areaMin'       => 'areaMin',
          'areaMax'       => 'areaMax',
          'hasMortgage'   => 'hasMortgage',
          'hasBillOfSale' => 'hasBillOfSale',
          'notFirstFloor' => 'notFirstFloor',
          'notTopFloor'   => 'notTopFloor',
          'onlyTopFloor'  => 'onlyTopFloor',
        ]
--}}


<div class="flex flex-wrap items-end gap-2">

    {{-- Kateqoriya --}}
    <div class="w-40">
        <flux:select wire:model="{{ $f['category'] }}" placeholder="Kateqoriya" size="sm">
            <flux:select.option value="">Kateqoriya</flux:select.option>
            @foreach($categories as $cat)
                <flux:select.option value="{{ $cat->id }}">{{ $cat->name_az }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Ərazi multi-select --}}
    @php
        $locField = $f['locationIds'];
        $locationsJs = $locations->map(fn($l) => ['id' => (string)$l->id, 'name' => $l->name_az]);
    @endphp
    <div x-data="{
            open: false,
            search: '',
            locations: {{ Js::from($locationsJs) }},
            get filtered() {
                if (!this.search) return this.locations;
                const s = this.search.toLowerCase();
                return this.locations.filter(l => l.name.toLowerCase().includes(s));
            },
            toggle(id) {
                const ids = [...($wire.{{ $locField }} || [])];
                const idx = ids.indexOf(id);
                if (idx > -1) { ids.splice(idx, 1); } else { ids.push(id); }
                $wire.set('{{ $locField }}', ids);
            },
            isSelected(id) { return ($wire.{{ $locField }} || []).includes(id); }
         }"
         class="relative w-44"
         @keydown.escape="open = false">
        <button type="button" @click="open = !open"
            class="relative flex h-8 w-full items-center justify-between rounded-md border border-zinc-200 bg-white pl-2.5 pr-7 text-left text-sm shadow-xs outline-none transition hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800">
            <span :class="($wire.{{ $locField }} || []).length ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400'"
                  x-text="($wire.{{ $locField }} || []).length ? ($wire.{{ $locField }} || []).length + ' ərazi' : 'Ərazi'"
                  class="truncate text-xs"></span>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5">
                <flux:icon.chevron-up-down class="size-3.5 text-zinc-400" />
            </span>
        </button>
        <div x-show="open" @click.away="open = false" x-transition.opacity
             class="absolute z-50 mt-1 max-h-60 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
            <div class="border-b border-zinc-100 p-1.5 dark:border-zinc-700">
                <input x-model="search" type="text" placeholder="Axtar..."
                       class="w-full rounded border-0 bg-zinc-50 px-2 py-1 text-xs outline-none placeholder:text-zinc-400 focus:ring-0 dark:bg-zinc-700 dark:text-white"
                       @click.stop />
            </div>
            <div class="max-h-44 overflow-y-auto">
                <template x-for="loc in filtered" :key="loc.id">
                    <button type="button" @click.stop="toggle(loc.id)"
                            :class="isSelected(loc.id) ? 'bg-zinc-100 dark:bg-zinc-700' : 'hover:bg-zinc-50 dark:hover:bg-zinc-700/50'"
                            class="flex w-full items-center gap-2 px-2.5 py-1 text-left text-xs transition">
                        <span :class="isSelected(loc.id) ? 'bg-indigo-600 border-indigo-600' : 'border-zinc-300 dark:border-zinc-500'"
                              class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border transition">
                            <svg x-show="isSelected(loc.id)" class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <span x-text="loc.name" class="text-zinc-700 dark:text-zinc-300"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Otaq sayı --}}
    <div class="w-24">
        <flux:select wire:model="{{ $f['roomMin'] }}" placeholder="Otaq min" size="sm">
            <flux:select.option value="">Otaq min</flux:select.option>
            @for($i = 1; $i <= 6; $i++)
                <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
            @endfor
        </flux:select>
    </div>
    <div class="w-24">
        <flux:select wire:model="{{ $f['roomMax'] }}" placeholder="Otaq max" size="sm">
            <flux:select.option value="">Otaq max</flux:select.option>
            @for($i = 1; $i <= 6; $i++)
                <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
            @endfor
        </flux:select>
    </div>

    {{-- Qiymət --}}
    <div class="w-28">
        <flux:input wire:model="{{ $f['priceMin'] }}" placeholder="Min ₼" type="number" size="sm" />
    </div>
    <div class="w-28">
        <flux:input wire:model="{{ $f['priceMax'] }}" placeholder="Max ₼" type="number" size="sm" />
    </div>

    {{-- Mərtəbə --}}
    <div class="w-24">
        <flux:input wire:model="{{ $f['floorMin'] }}" placeholder="Mərt. min" type="number" size="sm" min="1" />
    </div>
    <div class="w-24">
        <flux:input wire:model="{{ $f['floorMax'] }}" placeholder="Mərt. max" type="number" size="sm" min="1" />
    </div>

    {{-- Sahə --}}
    <div class="w-24">
        <flux:input wire:model="{{ $f['areaMin'] }}" placeholder="m² min" type="number" size="sm" min="1" />
    </div>
    <div class="w-24">
        <flux:input wire:model="{{ $f['areaMax'] }}" placeholder="m² max" type="number" size="sm" min="1" />
    </div>

</div>

{{-- Boolean filtrlər --}}
<div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
    @foreach([
        ['field' => $f['hasBillOfSale'],  'label' => 'Çıxarış var'],
        ['field' => $f['hasMortgage'],    'label' => 'İpoteka var'],
        ['field' => $f['notFirstFloor'],  'label' => '1-ci olmasın'],
        ['field' => $f['notTopFloor'],    'label' => 'Ən üst olmasın'],
        ['field' => $f['onlyTopFloor'],   'label' => 'Yalnız ən üst'],
    ] as $opt)
    <label class="flex cursor-pointer items-center gap-1.5 select-none">
        <input type="checkbox"
               wire:model="{{ $opt['field'] }}"
               class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800" />
        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $opt['label'] }}</span>
    </label>
    @endforeach
</div>

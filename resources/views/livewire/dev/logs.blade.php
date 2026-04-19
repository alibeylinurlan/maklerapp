<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $activeLog = 'telegram';
    public string $lines = '';
    public array $availableDates = [];
    public string $selectedDate = '';

    public function mount(): void
    {
        $this->loadDates();
        $this->loadLog();
    }

    public function updatedActiveLog(): void
    {
        $this->loadDates();
        $this->loadLog();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadLog();
    }

    private function loadDates(): void
    {
        $pattern = storage_path("logs/{$this->activeLog}*.log");
        $files = glob($pattern);
        rsort($files);
        $this->availableDates = array_map(fn($f) => basename($f), $files);
        $this->selectedDate = $this->availableDates[0] ?? '';
    }

    private function loadLog(): void
    {
        if (!$this->selectedDate) {
            $this->lines = '';
            return;
        }
        $path = storage_path("logs/{$this->selectedDate}");
        if (!file_exists($path)) {
            $this->lines = '';
            return;
        }
        $limit = $this->activeLog === 'laravel' ? 1000 : 200;
        $allLines = array_filter(explode("\n", file_get_contents($path)));
        $lastLines = array_slice(array_values($allLines), -$limit);
        $this->lines = implode("\n", array_reverse($lastLines));
    }

    public function refresh(): void
    {
        $this->loadLog();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Developer Loglar</flux:heading>
        <flux:button wire:click="refresh" icon="arrow-path" size="sm" variant="ghost">Yenilə</flux:button>
    </div>

    <div class="flex items-center gap-3 mb-4">
        <div class="flex gap-2">
            <flux:button wire:click="$set('activeLog', 'telegram')"
                variant="{{ $activeLog === 'telegram' ? 'primary' : 'ghost' }}" size="sm">
                Telegram
            </flux:button>
            <flux:button wire:click="$set('activeLog', 'laravel')"
                variant="{{ $activeLog === 'laravel' ? 'primary' : 'ghost' }}" size="sm">
                Laravel
            </flux:button>
        </div>

        @if(count($availableDates) > 0)
        <flux:select wire:model.live="selectedDate" size="sm" class="w-56">
            @foreach($availableDates as $file)
                <flux:select.option value="{{ $file }}">{{ $file }}</flux:select.option>
            @endforeach
        </flux:select>
        @endif
    </div>

    @if($lines)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-950 p-4 overflow-auto max-h-[70vh]">
            <pre class="text-xs text-zinc-300 leading-relaxed whitespace-pre-wrap break-all font-mono">{{ $lines }}</pre>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 p-12 text-center text-zinc-400">
            Log faylı tapılmadı
        </div>
    @endif
</div>

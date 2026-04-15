<?php

use App\Models\PropertyMatch;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'new';

    public function updatedStatus(): void { $this->resetPage(); }

    public function markViewed(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)
            ->update(['status' => 'viewed']);
    }

    public function dismiss(int $id): void
    {
        PropertyMatch::where('user_id', auth()->id())->findOrFail($id)
            ->update(['status' => 'dismissed']);
    }

    public function with(): array
    {
        $query = PropertyMatch::with(['property', 'customerRequest.customer'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $user = auth()->user();
        $canAccess = $user->hasAnyRole(['superadmin', 'admin']) || $user->hasPlan('matches');

        if (!$canAccess) {
            return ['canAccess' => false, 'matches' => collect(), 'counts' => []];
        }

        return [
            'canAccess' => true,
            'matches' => $query->paginate(20),
            'counts' => [
                'new' => PropertyMatch::where('user_id', auth()->id())->where('status', 'new')->count(),
                'viewed' => PropertyMatch::where('user_id', auth()->id())->where('status', 'viewed')->count(),
                'dismissed' => PropertyMatch::where('user_id', auth()->id())->where('status', 'dismissed')->count(),
            ],
        ];
    }
}; ?>

<div>
@if(!$canAccess)
    @include('livewire.partials.plan-gate', ['planKey' => 'matches', 'planName' => 'Uyğunluqlar', 'pageTitle' => 'Uyğunluqlar'])
@else
    <flux:heading size="xl">Uyğunluqlar</flux:heading>

    <div class="mt-4 flex gap-2">
        <flux:button wire:click="$set('status', 'new')" variant="{{ $status === 'new' ? 'primary' : 'ghost' }}" size="sm">
            Yeni ({{ $counts['new'] }})
        </flux:button>
        <flux:button wire:click="$set('status', 'viewed')" variant="{{ $status === 'viewed' ? 'primary' : 'ghost' }}" size="sm">
            Baxılıb ({{ $counts['viewed'] }})
        </flux:button>
        <flux:button wire:click="$set('status', 'dismissed')" variant="{{ $status === 'dismissed' ? 'primary' : 'ghost' }}" size="sm">
            Keçildi ({{ $counts['dismissed'] }})
        </flux:button>
    </div>

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>Şəkil</flux:table.column>
            <flux:table.column>Müştəri / İstək</flux:table.column>
            <flux:table.column>Qiymət</flux:table.column>
            <flux:table.column>Otaq</flux:table.column>
            <flux:table.column>Sahə</flux:table.column>
            <flux:table.column>Ərazi</flux:table.column>
            <flux:table.column>Tapıldı</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($matches as $match)
            <flux:table.row>
                <flux:table.cell>
                    @if($match->property?->photos && count($match->property->photos) > 0)
                        <img src="{{ $match->property->photos[0] }}" alt="" class="h-12 w-16 rounded object-cover" loading="lazy">
                    @else
                        <div class="flex h-12 w-16 items-center justify-center rounded bg-zinc-200 text-xs text-zinc-400">Yox</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    <div class="font-medium">{{ $match->customerRequest?->customer?->name }}</div>
                    <div class="text-xs text-zinc-500">{{ $match->customerRequest?->name }}</div>
                </flux:table.cell>
                <flux:table.cell class="font-semibold">{{ number_format($match->property?->price) }} {{ $match->property?->currency }}</flux:table.cell>
                <flux:table.cell>{{ $match->property?->rooms }}</flux:table.cell>
                <flux:table.cell>{{ $match->property?->area }} m²</flux:table.cell>
                <flux:table.cell class="max-w-40 truncate">{{ $match->property?->location_full_name }}</flux:table.cell>
                <flux:table.cell class="text-xs text-zinc-500">{{ $match->created_at->diffForHumans() }}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex gap-1">
                        <a href="{{ $match->property?->full_url }}" target="_blank">
                            <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" />
                        </a>
                        @if($match->status === 'new')
                            <flux:button wire:click="markViewed({{ $match->id }})" size="xs" variant="ghost" icon="eye" />
                        @endif
                        @if($match->status !== 'dismissed')
                            <flux:button wire:click="dismiss({{ $match->id }})" size="xs" variant="ghost" icon="x-mark" class="text-red-500" />
                        @endif
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @empty
            <flux:table.row>
                <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                    Bu statusda uyğunluq yoxdur.
                </flux:table.cell>
            </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $matches->links() }}</div>
@endif
</div>

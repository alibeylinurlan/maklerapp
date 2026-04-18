<?php

use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Property;
use App\Models\PropertyMatch;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $userId = auth()->id();

        return [
            'customerCount' => Customer::where('user_id', $userId)->count(),
            'requestCount' => CustomerRequest::where('user_id', $userId)->where('is_active', true)->count(),
            'matchCount' => PropertyMatch::where('user_id', $userId)->where('status', 'new')->count(),
            'propertyCount' => Property::count(),
            'customerRequests' => CustomerRequest::with([
                    'matches' => fn($q) => $q->where('status', 'new')->latest(),
                    'customer'
                ])
                ->withCount([
                    'matches as new_matches_count' => fn($q) => $q->where('status', 'new')
                ])
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->get()
                ->filter(fn($r) => $r->matches->isNotEmpty())
                ->sortByDesc(fn($r) => $r->matches->max('created_at'))
                ->take(10)
                ->values(),
        ];
    }
}; ?>

<div>
    <flux:heading size="xl">Dashboard</flux:heading>
    <flux:subheading>Xoş gəldiniz, {{ auth()->user()->name }}</flux:subheading>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Müştərilərim <em class="text-[10px] font-normal text-zinc-400 italic">(alıcılar)</em></div>
            <div class="mt-1 text-2xl font-bold">{{ $customerCount }}</div>
        </flux:card>
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Aktiv istəklər</div>
            <div class="mt-1 text-2xl font-bold">{{ $requestCount }}</div>
        </flux:card>
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Yeni uyğunluqlar</div>
            <div class="mt-1 text-2xl font-bold text-green-600">{{ $matchCount }}</div>
        </flux:card>
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Elan bazası</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($propertyCount) }}</div>
        </flux:card>
    </div>

    @if($customerRequests->isNotEmpty())
    <flux:heading size="lg" class="mt-8">Son uyğunluqlar</flux:heading>
    <flux:table class="mt-3">
        <flux:table.columns>
            <flux:table.column>İstək</flux:table.column>
            <flux:table.column>Uyğunluq sayı</flux:table.column>
            <flux:table.column>Son uyğunluq</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($customerRequests as $request)
                <flux:table.row>
                    <flux:table.cell>{{ $request->name }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:badge color="green" size="sm">
                            {{ $request->new_matches_count }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-zinc-500">
                        {{ $request->matches->max('created_at')?->diffForHumans() }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    @else
    <div class="mt-8 rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500">
        Hələ uyğunluq tapılmayıb. Müştəri istəkləri əlavə edin.
    </div>
    @endif
</div>

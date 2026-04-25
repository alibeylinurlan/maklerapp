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

        $isDeveloper = auth()->user()->hasRole('developer');

        return [
            'customerCount' => Customer::where('user_id', $userId)->count(),
            'requestCount' => CustomerRequest::where('user_id', $userId)->where('is_active', true)->count(),
            'matchCount' => PropertyMatch::where('user_id', $userId)->where('status', 'new')->count(),
            'propertyCount' => $isDeveloper ? Property::count() : null,
            'isDeveloper' => $isDeveloper,
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

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $isDeveloper ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
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
        @if($isDeveloper)
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Elan bazası</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($propertyCount) }}</div>
        </flux:card>
        @endif
    </div>

    @if($customerRequests->isNotEmpty())
    <flux:heading size="lg" class="mt-8">Son uyğunluqlar</flux:heading>
    <flux:table class="mt-3">
        <flux:table.columns>
            <flux:table.column>Müştəri / İstək</flux:table.column>
            <flux:table.column>Uyğunluq</flux:table.column>
            <flux:table.column>Son tapılma</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($customerRequests as $request)
                <flux:table.row
                    class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                    onclick="window.location='{{ route('customers.index', ['selectedCustomerId' => $request->customer_id, 'selectedRequestId' => $request->id]) }}'">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $request->customer?->name }}</div>
                        <div class="text-xs text-zinc-400 mt-0.5">{{ $request->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="green" size="sm">{{ $request->new_matches_count }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-400">
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

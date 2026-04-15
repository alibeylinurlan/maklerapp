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
            'recentMatches' => PropertyMatch::with(['property', 'customerRequest.customer'])
                ->where('user_id', $userId)
                ->where('status', 'new')
                ->latest()
                ->take(10)
                ->get(),
        ];
    }
}; ?>

<div>
    <flux:heading size="xl">Dashboard</flux:heading>
    <flux:subheading>Xoş gəldiniz, {{ auth()->user()->name }}</flux:subheading>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="!p-4">
            <div class="text-sm text-zinc-500">Müştərilər</div>
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

    @if($recentMatches->isNotEmpty())
    <flux:heading size="lg" class="mt-8">Son uyğunluqlar</flux:heading>
    <flux:table class="mt-3">
        <flux:table.columns>
            <flux:table.column>Müştəri</flux:table.column>
            <flux:table.column>Elan</flux:table.column>
            <flux:table.column>Qiymət</flux:table.column>
            <flux:table.column>Otaq</flux:table.column>
            <flux:table.column>Ərazi</flux:table.column>
            <flux:table.column>Tarix</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($recentMatches as $match)
            <flux:table.row>
                <flux:table.cell>{{ $match->customerRequest?->customer?->name ?? '-' }}</flux:table.cell>
                <flux:table.cell>
                    <a href="{{ $match->property?->full_url }}" target="_blank" class="text-indigo-600 hover:underline">
                        {{ $match->property?->bina_id }}
                    </a>
                </flux:table.cell>
                <flux:table.cell>{{ number_format($match->property?->price) }} {{ $match->property?->currency }}</flux:table.cell>
                <flux:table.cell>{{ $match->property?->rooms }}</flux:table.cell>
                <flux:table.cell class="max-w-48 truncate">{{ $match->property?->location_full_name }}</flux:table.cell>
                <flux:table.cell>{{ $match->created_at->diffForHumans() }}</flux:table.cell>
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

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
                    'customer:id,name',
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
            'telegramRequests' => CustomerRequest::select('id', 'name', 'customer_id', 'user_id')
                ->with('customer:id,name')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('notify_telegram', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div>
    <flux:heading size="xl">Giriş səhifəsi</flux:heading>
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

    @if($telegramRequests->isNotEmpty())
    <div class="mt-8">
        <div class="flex items-center gap-2 mb-3">
            <svg class="size-4 text-sky-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
            <flux:heading size="lg">Aktiv Telegram bildirişləri</flux:heading>
            <flux:badge size="sm" color="sky">{{ $telegramRequests->count() }}</flux:badge>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($telegramRequests as $req)
            <a href="{{ route('customers.index', ['selectedCustomerId' => $req->customer_id, 'selectedRequestId' => $req->id]) }}"
               class="flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-sm text-sky-700 hover:bg-sky-100 transition dark:border-sky-800 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/40">
                <span class="size-1.5 rounded-full bg-sky-400 shrink-0"></span>
                <span class="font-medium">{{ $req->customer?->name }}</span>
                <span class="text-sky-400">·</span>
                <span>{{ $req->name }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

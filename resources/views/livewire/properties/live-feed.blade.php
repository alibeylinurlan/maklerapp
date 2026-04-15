<?php
use App\Models\Property;
use Livewire\Volt\Component;

new class extends Component {
    public int $maxId = 0;

    public function mount(): void
    {
        $this->maxId = Property::where('is_owner', true)->max('id') ?? 0;
    }
};
?>

<div x-data="liveFeed({{ $maxId }})" class="flex flex-col h-full">

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
        <div class="flex items-center gap-2">
            <span class="relative flex size-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full opacity-75"
                      :class="connected ? 'animate-ping bg-emerald-400' : 'bg-zinc-600'"></span>
                <span class="relative inline-flex size-2.5 rounded-full"
                      :class="connected ? 'bg-emerald-500' : 'bg-zinc-600'"></span>
            </span>
            <span class="text-sm font-semibold text-white tracking-wide">Canlı | Yeni elanlar</span>
        </div>
        @if(auth()->user()->hasAnyRole(['developer', 'superadmin', 'admin']))
        <button @click="sendTest()"
                class="rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 border border-indigo-500/40 text-indigo-400 hover:bg-indigo-500/30 transition-colors">
            Test
        </button>
        @endif
    </div>

    {{-- Empty state --}}
    <div x-show="items.length === 0"
         class="flex flex-col items-center justify-center flex-1 gap-3 text-white/25 py-12">
        <svg class="size-10" :class="connected ? '' : 'animate-pulse'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 3.75h5.25a.75.75 0 01.75.75v5.25a.75.75 0 01-.75.75H13.5a.75.75 0 01-.75-.75V4.5a.75.75 0 01.75-.75zM3.75 13.5h5.25a.75.75 0 01.75.75V19.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75v-5.25a.75.75 0 01.75-.75zM13.5 15.75a2.25 2.25 0 114.5 0 2.25 2.25 0 01-4.5 0z"/>
        </svg>
        <p class="text-sm text-center px-4 leading-relaxed" x-text="connected ? 'Yeni elanlar\nburada görünəcək' : 'Qoşulur...'"></p>
    </div>

    {{-- Feed --}}
    <div x-show="items.length > 0" class="flex-1 overflow-y-auto divide-y divide-white/5 p-2">
        <template x-for="item in items" :key="item.id">
            <a :href="item.url" target="_blank"
               class="flex gap-3 px-3 py-2.5 hover:bg-white/5 transition-colors group mb-2"
               x-bind:class="item.isNew ? 'feed-new' : ''">

                <div class="shrink-0 size-12 overflow-hidden rounded-lg bg-white/10">
                    <template x-if="item.thumb">
                        <img :src="item.thumb" class="size-full object-cover" loading="lazy">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="size-full flex items-center justify-center text-white/20">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 21h18M6.75 6.75h.008v.008H6.75V6.75z"/>
                            </svg>
                        </div>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-1">
                        <span class="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors"
                              x-text="item.price || 'Qiymət yox'"></span>
                        <span x-show="item.isNew"
                              class="shrink-0 rounded-sm bg-emerald-500/30 border border-emerald-500/40 px-1 py-0.5 text-[9px] font-bold uppercase tracking-widest text-emerald-400">
                            YENİ
                        </span>
                    </div>

                    <div class="mt-0.5 flex flex-wrap items-center gap-x-1 text-xs text-white/50">
                        <span x-show="item.category" x-text="item.category" class="text-indigo-400/80"></span>
                        <template x-if="item.category && (item.rooms || item.area)">
                            <span>·</span>
                        </template>
                        <span x-show="item.rooms" x-text="item.rooms + 'otaq'"></span>
                        <template x-if="item.rooms && item.area">
                            <span>·</span>
                        </template>
                        <span x-show="item.area" x-text="item.area + 'm²'"></span>
                        <template x-if="item.floor">
                            <span x-text="'· ' + item.floor + (item.floor_total ? '/' + item.floor_total : '') + 'm'"></span>
                        </template>
                    </div>

                    <div x-show="item.location" class="mt-0.5 truncate text-xs text-white/35" x-text="item.location"></div>
                </div>
            </a>
        </template>
    </div>

</div>

<style>
.feed-new {
    position: relative;
    animation: feedIn 0.4s ease both;
}

.feed-new::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 0.5rem;
    border: 1px solid rgba(16, 185, 129, 0.6);
    animation: pulseBorder 1.2s ease-out infinite;
    pointer-events: none;
}

@keyframes pulseBorder {
    0% {
        opacity: 0.8;
        transform: scale(1);
    }
    70% {
        opacity: 0;
        transform: scale(1.05);
    }
    100% {
        opacity: 0;
        transform: scale(1.05);
    }
}
@keyframes feedIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
        background: rgba(16,185,129,0.1);
    }
    to {
        opacity: 1;
        transform: translateY(0);
        background: transparent;
    }
}
</style>

<script>
function liveFeed(initialMaxId) {
    return {
        items: [],
        newCount: 0,
        lastKnownId: initialMaxId,
        connected: false,
        socket: null,

        init() {
            this.connect();
        },

        connect() {
            if (typeof io === 'undefined') {
                setTimeout(() => this.connect(), 300);
                return;
            }

            this.socket = io({
                path: '/socket.io/',
                transports: ['websocket', 'polling'],
                reconnectionDelay: 2000,
                reconnectionAttempts: Infinity,
            });

            this.socket.on('connect', () => {
                this.connected = true;
            });

            this.socket.on('disconnect', () => {
                this.connected = false;
            });

            this.socket.on('property.created', (data) => {
                if (this.items.some(i => i.id === data.id)) return;
                this.newCount++;

                const item = { ...data, isNew: true };
                this.items = [item, ...this.items].slice(0, 50);

                // 5 saniyə sonra YENİ badge-i sil
                setTimeout(() => {
                    const found = this.items.find(i => i.id === item.id);
                    if (found) found.isNew = false;
                }, 5000);
            });
        },

        sendTest() {
            fetch('/dev/test-socket', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
            });
        },

        destroy() {
            if (this.socket) this.socket.disconnect();
        }
    };
}
</script>

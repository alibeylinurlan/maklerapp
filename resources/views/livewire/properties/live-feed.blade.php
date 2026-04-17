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

<div x-data="liveFeed({{ $maxId }})"
     x-init="init()"
     class="flex flex-col h-full">

    {{-- HEADER --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
        <div class="flex items-center gap-2">
            <span class="relative flex size-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full opacity-75"
                      :class="connected ? 'animate-ping bg-emerald-400' : 'bg-zinc-600'"></span>
                <span class="relative inline-flex size-2.5 rounded-full"
                      :class="connected ? 'bg-emerald-500' : 'bg-zinc-600'"></span>
            </span>

            <span class="text-sm font-semibold text-white tracking-wide">
                Canlı | Yeni elanlar
            </span>
        </div>

        @if(auth()->user()->hasAnyRole(['developer']))
        <button @click="sendTest()"
                class="rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 border border-indigo-500/40 text-indigo-400 hover:bg-indigo-500/30 transition-colors">
            Test
        </button>
        @endif
    </div>

    {{-- EMPTY --}}
    <div x-show="items.length === 0"
         class="flex flex-col items-center justify-center flex-1 gap-3 text-white/25 py-12">

        <svg class="size-10"
             :class="connected ? '' : 'animate-pulse'"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 3.75h5.25a.75.75 0 01.75.75v5.25a.75.75 0 01-.75.75H13.5a.75.75 0 01-.75-.75V4.5a.75.75 0 01.75-.75zM3.75 13.5h5.25a.75.75 0 01.75.75V19.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75v-5.25a.75.75 0 01.75-.75zM13.5 15.75a2.25 2.25 0 114.5 0 2.25 2.25 0 01-4.5 0z"/>
        </svg>

        <p class="text-sm text-center px-4 leading-relaxed"
           x-text="connected ? 'Yeni elanlar\nburada görünəcək' : 'Qoşulur...'"></p>
    </div>

    {{-- FEED --}}
    <div x-show="items.length > 0"
         class="flex-1 overflow-y-auto divide-y divide-white/5 p-2">

        <template x-for="item in items" :key="item.id">

            <div class="feed-slot">

                <a :href="item.url"
                   target="_blank"
                   class="flex gap-3 px-3 py-2.5 hover:bg-white/5 transition-colors group mb-2"
                   :class="item.isNew ? 'feed-enter feed-glow' : 'feed-enter'">

                    {{-- IMAGE --}}
                    <div class="shrink-0 size-12 overflow-hidden rounded-lg bg-white/10">

                        <template x-if="item.thumb">
                            <img :src="item.thumb"
                                 class="size-full object-cover"
                                 loading="lazy">
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

                    {{-- CONTENT --}}
                    <div class="min-w-0 flex-1">

                        <div class="flex items-start justify-between gap-1">
                            <span class="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors"
                                  x-text="item.price || 'Qiymət yox'"></span>

                            <span x-show="item.isNew"
                                  class="blink-soft shrink-0 rounded-sm bg-emerald-500/30 border border-emerald-500/40 px-1 py-0.5 text-[9px] font-bold uppercase tracking-widest text-emerald-400">
                                YENİ
                            </span>
                        </div>

                        <div class="mt-0.5 flex flex-wrap items-center gap-x-1 text-xs text-white/50">

                            <span x-show="item.category"
                                  x-text="item.category"
                                  class="text-indigo-400/80"></span>

                            <template x-if="item.category && (item.rooms || item.area)">
                                <span>·</span>
                            </template>

                            <span x-show="item.rooms"
                                  x-text="item.rooms + ' otaq'"></span>

                            <template x-if="item.rooms && item.area">
                                <span>·</span>
                            </template>

                            <span x-show="item.area"
                                  x-text="item.area + ' m²'"></span>

                            <template x-if="item.floor">
                                <span x-text="'· ' + item.floor + (item.floor_total ? '/' + item.floor_total : '')"></span>
                            </template>
                        </div>

                        <div x-show="item.location"
                             class="mt-0.5 truncate text-xs text-white"
                             x-text="item.location"></div>

                        {{-- TIME --}}
                        <div class="mt-1 text-[10px] text-white/50"
                             x-text="formatTime(item.created_at)"></div>

                    </div>
                </a>

            </div>

        </template>
    </div>
</div>

<style>
.feed-slot {
    overflow: hidden;
    animation: slotOpen 0.35s ease-out both;
}

@keyframes slotOpen {
    from { max-height: 0; opacity: 0; }
    to { max-height: 200px; opacity: 1; }
}

.feed-enter {
    animation: feedEnter 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes feedEnter {
    0% {
        opacity: 0;
        transform: translateY(-14px) scale(0.98);
        filter: blur(2px);
    }
    60% {
        opacity: 1;
        transform: translateY(2px) scale(1.01);
        filter: blur(0);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* CLEAN GLOW (NO PULSE, NO JITTER) */
.feed-glow {
    position: relative;
}

.feed-glow::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 0.5rem;
    pointer-events: none;

    border: 1px solid rgba(16, 185, 129, 0.35);
    box-shadow: 0 0 18px rgba(16, 185, 129, 0.25);

    animation: glowOnce 1.2s ease-out forwards;
}

@keyframes glowOnce {
    0% {
        opacity: 0;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0);
    }
    30% {
        opacity: 1;
        box-shadow: 0 0 14px rgba(16, 185, 129, 0.25);
    }
    100% {
        opacity: 0;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0);
    }
}
</style>

<script>
function liveFeed(initialMaxId) {
    return {
        items: [],
        connected: false,
        socket: null,
        tick: Date.now(),
        lastId: initialMaxId,

        init() {
            this.connect();

            setInterval(() => {
                this.tick = Date.now();
                this.items = this.items.map(i => i);
            }, 1000);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.fetchMissed();
                }
            });
        },

        fetchMissed() {
            fetch(`/api/properties/new?since=${this.lastId}`)
                .then(r => r.json())
                .then(list => list.forEach(data => this.addItem(data, false)))
                .catch(() => {});
        },

        addItem(data, isNew = true) {
            if (this.items.some(i => i.id === data.id)) return;
            if (data.id > this.lastId) this.lastId = data.id;

            const item = { ...data, isNew, created_at: data.created_at ?? new Date().toISOString() };
            this.items = [item, ...this.items].slice(0, 50);

            if (isNew) {
                setTimeout(() => {
                    const found = this.items.find(i => i.id === item.id);
                    if (found) found.isNew = false;
                }, 30000);
            }
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

            this.socket.on('connect', () => this.connected = true);
            this.socket.on('disconnect', () => this.connected = false);

            this.socket.on('property.created', (data) => {
                this.addItem(data, true);
            });
        },

        formatTime(dateString) {
            const date = new Date(dateString);
            const diff = Math.floor((this.tick - date.getTime()) / 1000);

            if (diff < 10) return 'indi';
            if (diff < 60) return `${diff} san. əvvəl`;

            const min = Math.floor(diff / 60);
            if (min < 60) return `${min} dəq əvvəl`;

            const hour = Math.floor(min / 60);
            if (hour < 24) return `${hour} saat əvvəl`;

            const day = Math.floor(hour / 24);
            return `${day} gün əvvəl`;
        },

        sendTest() {
            const colors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#0284c7'];
            const bg = colors[Math.floor(Math.random() * colors.length)];
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200"><rect width="300" height="200" fill="${bg}"/><rect x="110" y="80" width="80" height="60" rx="4" fill="rgba(255,255,255,0.15)"/><polygon points="100,85 150,45 200,85" fill="rgba(255,255,255,0.2)"/></svg>`;
            const thumb = 'data:image/svg+xml;base64,' + btoa(svg);
            const locations = ['Bakı, Nəsimi r.', 'Bakı, Yasamal r.', 'Bakı, Sabunçu r.', 'Bakı, Xətai r.', 'Bakı, Binəqədi r.'];
            this.addItem({
                id: 999000 + Math.floor(Math.random() * 999),
                price: new Intl.NumberFormat().format(Math.floor(Math.random() * 160000) + 40000) + ' ₼',
                rooms: Math.floor(Math.random() * 5) + 1,
                area: Math.floor(Math.random() * 110) + 40,
                floor: Math.floor(Math.random() * 12) + 1,
                floor_total: 16,
                location: locations[Math.floor(Math.random() * locations.length)],
                category: 'Mənzil',
                thumb,
                url: '#',
                created_at: new Date().toISOString(),
            }, true);
        }
    };
}
</script>
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

<div x-data="liveFeedH({{ $maxId }})"
     x-init="init()"
     class="flex flex-col h-full">

    {{-- HEADER --}}
    <div class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
        <span class="relative flex size-2">
            <span class="absolute inline-flex h-full w-full rounded-full opacity-75"
                  :class="connected ? 'animate-ping bg-emerald-400' : 'bg-zinc-600'"></span>
            <span class="relative inline-flex size-2 rounded-full"
                  :class="connected ? 'bg-emerald-500' : 'bg-zinc-600'"></span>
        </span>
        <span class="text-xs font-semibold text-white/80 tracking-wide">Canlı elanlar</span>
        <!--<span class="text-xs text-white/30 ml-1" x-show="items.length > 0" x-text="items.length + ' elan'"></span>-->
        @if(auth()->user()->hasAnyRole(['superadmin', 'admin', 'developer']))
        <button @click="sendTest()"
                class="ml-auto rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 border border-indigo-500/40 text-indigo-400 hover:bg-indigo-500/30 transition-colors">
            Test
        </button>
        @endif
    </div>

    {{-- FEED --}}
    <div class="flex-1 overflow-x-auto overflow-y-hidden pb-4">
        <div class="flex gap-2 px-3 py-2 h-full items-stretch"
             style="width: max-content; min-width: 100%;">

            {{-- Empty state --}}
            <div x-show="items.length === 0"
                 class="flex items-center justify-center w-full text-white/25 text-xs"
                 x-text="connected ? 'Yeni elanlar burada görünəcək...' : 'Qoşulur...'">
            </div>

            <template x-for="item in items" :key="item.id">
                <a :href="item.url"
                   target="_blank"
                   :class="item.isNew ? 'feed-card-new' : ''"
                   class="feed-card shrink-0 relative overflow-hidden rounded-xl border border-white/10 hover:border-white/25 transition-all hover:scale-[1.02] hover:shadow-xl"
                   style="width: 150px; height: 110px;">

                    {{-- BG image --}}
                    <template x-if="item.thumb">
                        <img :src="item.thumb" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="absolute inset-0" style="background: linear-gradient(135deg, #312e81 0%, #1e3a5f 100%)"></div>
                    </template>

                    {{-- Gradient overlay --}}
                    <div class="absolute inset-0" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 55%, rgba(0,0,0,0.05) 100%)"></div>

                    {{-- NEW badge --}}
                    <template x-if="item.isNew">
                        <span class="absolute top-1.5 right-1.5 blink-soft rounded-sm bg-emerald-500/30 border border-emerald-500/50 px-1 py-0.5 text-[8px] font-bold uppercase tracking-widest text-emerald-400 backdrop-blur-sm">YENİ</span>
                    </template>

                    {{-- Category chip --}}
                    <template x-if="item.category">
                        <span class="absolute top-1.5 left-1.5 rounded-md bg-black/40 backdrop-blur-sm px-1.5 py-0.5 text-[9px] text-white/70"
                              x-text="item.category"></span>
                    </template>

                    {{-- Info overlay --}}
                    <div class="absolute bottom-0 left-0 right-0 px-2 pb-2">
                        <div class="text-sm font-bold text-white leading-tight drop-shadow"
                             x-text="item.price || '—'"></div>
                        <div class="flex items-center gap-1 mt-0.5">
                            <span x-show="item.rooms" x-text="item.rooms + ' otaq'"
                                  class="text-[10px] text-white/60"></span>
                            <template x-if="item.rooms && item.area">
                                <span class="text-white/30 text-[10px]">·</span>
                            </template>
                            <span x-show="item.area" x-text="item.area + ' m²'"
                                  class="text-[10px] text-white/60"></span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5 gap-1">
                            <div x-show="item.location"
                                 class="text-[9px] text-white/40 truncate"
                                 x-text="item.location"></div>
                            <div class="text-[9px] text-white/30 shrink-0"
                                 x-text="formatTime(item.created_at)"></div>
                        </div>
                    </div>
                </a>
            </template>
        </div>
    </div>
</div>

<style>
.feed-card {
    animation: cardSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes cardSlideIn {
    from { opacity: 0; transform: translateX(-16px) scale(0.97); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}
.feed-card-new {
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.3) !important;
}
</style>

<script>
function liveFeedH(initialMaxId) {
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
                if (document.visibilityState === 'visible') this.fetchMissed();
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
            this.items = [item, ...this.items].slice(0, 40);
            if (isNew) {
                setTimeout(() => {
                    const found = this.items.find(i => i.id === item.id);
                    if (found) found.isNew = false;
                }, 5000);
            }
        },

        connect() {
            if (typeof io === 'undefined') { setTimeout(() => this.connect(), 300); return; }
            this.socket = io({
                path: '/socket.io/',
                transports: ['websocket', 'polling'],
                reconnectionDelay: 2000,
                reconnectionAttempts: Infinity,
            });
            this.socket.on('connect', () => this.connected = true);
            this.socket.on('disconnect', () => this.connected = false);
            this.socket.on('property.created', (data) => this.addItem(data, true));
        },

        sendTest() {
            fetch('/dev/test-socket', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
            });
        },

        formatTime(dateString) {
            const date = new Date(dateString);
            const diff = Math.floor((this.tick - date.getTime()) / 1000);
            if (diff < 10) return 'indi';
            if (diff < 60) return `${diff} san.`;
            const min = Math.floor(diff / 60);
            if (min < 60) return `${min} dəq.`;
            const hour = Math.floor(min / 60);
            return `${hour} saat`;
        },
    };
}
</script>

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
    <div class="flex-1 overflow-x-auto overflow-y-hidden">
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
                   x-data
                   @mousemove="
                       $el.style.transition = 'transform 0.06s linear, box-shadow 0.06s linear';
                       const r = $el.getBoundingClientRect();
                       const x = $event.clientX - r.left, y = $event.clientY - r.top;
                       const cx = r.width/2, cy = r.height/2;
                       const rx = ((y-cy)/cy)*-18, ry = ((x-cx)/cx)*18;
                       $el.style.transform = `perspective(500px) rotateX(${rx}deg) rotateY(${ry}deg) scale3d(1.06,1.06,1.06)`;
                       $el.style.boxShadow = `${-ry*2}px ${rx*2}px 20px rgba(0,0,0,0.5)`;
                       $el.querySelectorAll('[data-depth]').forEach(child => {
                           const d = parseFloat(child.dataset.depth);
                           const tx = ry * 0.01745 * d;
                           const ty = -rx * 0.01745 * d;
                           child.style.transition = 'transform 0.06s linear';
                           child.style.transform = `translate(${tx}px, ${ty}px)`;
                       });
                   "
                   @mouseleave="
                       $el.style.transition = 'transform 0.4s ease, box-shadow 0.4s ease';
                       $el.style.transform = 'perspective(500px) rotateX(0deg) rotateY(0deg) scale3d(1,1,1)';
                       $el.style.boxShadow = '';
                       $el.querySelectorAll('[data-depth]').forEach(child => {
                           child.style.transition = 'transform 0.4s ease';
                           child.style.transform = 'translate(0,0)';
                       });
                   "
                   class="feed-card shrink-0 relative block rounded-xl border border-white/10"
                   style="width:150px;height:110px;overflow:hidden;will-change:transform;">

                    {{-- BG image --}}
                    <template x-if="item.thumb">
                        <img :src="item.thumb" class="absolute inset-0 w-full h-full object-cover rounded-xl">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="absolute inset-0 rounded-xl" style="background: linear-gradient(135deg, #312e81 0%, #1e3a5f 100%)"></div>
                    </template>

                    {{-- Gradient overlay --}}
                    <div class="absolute inset-0 rounded-xl" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 55%, rgba(0,0,0,0.05) 100%)"></div>

                    {{-- NEW badge --}}
                    <template x-if="item.isNew">
                        <span class="absolute top-1.5 right-1.5 blink-soft rounded-sm bg-emerald-500/30 border border-emerald-500/50 px-1 py-0.5 text-[8px] font-bold uppercase tracking-widest text-emerald-400"
                              data-depth="20">YENİ</span>
                    </template>

                    {{-- Category chip --}}
                    <template x-if="item.category">
                        <span class="absolute top-1.5 left-1.5 rounded-md bg-black/40 px-1.5 py-0.5 text-[9px] text-white/70"
                              x-text="item.category"
                              data-depth="12"></span>
                    </template>

                    {{-- Info overlay --}}
                    <div class="absolute bottom-0 left-0 right-0 px-2 pb-2"
                         data-depth="28">
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
    animation: cardFadeIn 0.4s ease both;
}
@keyframes cardFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.feed-card-new {
    animation: cardFadeIn 0.4s ease both, glowBlink 30s ease-out forwards;
}
@keyframes glowBlink {
    0%   { box-shadow: 0 0 5px rgba(16,185,129,0.5); border-color: rgba(16,185,129,0.7) !important; }
    2%   { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    4%   { box-shadow: 0 0 5px rgba(16,185,129,0.5); border-color: rgba(16,185,129,0.7) !important; }
    6%   { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    8%   { box-shadow: 0 0 5px rgba(16,185,129,0.5); border-color: rgba(16,185,129,0.7) !important; }
    10%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    12%  { box-shadow: 0 0 5px rgba(16,185,129,0.5); border-color: rgba(16,185,129,0.7) !important; }
    14%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    16%  { box-shadow: 0 0 5px rgba(16,185,129,0.5); border-color: rgba(16,185,129,0.7) !important; }
    18%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    20%  { box-shadow: 0 0 4px rgba(16,185,129,0.4); border-color: rgba(16,185,129,0.6) !important; }
    22%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    24%  { box-shadow: 0 0 4px rgba(16,185,129,0.4); border-color: rgba(16,185,129,0.6) !important; }
    26%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    30%  { box-shadow: 0 0 4px rgba(16,185,129,0.4); border-color: rgba(16,185,129,0.6) !important; }
    33%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    37%  { box-shadow: 0 0 4px rgba(16,185,129,0.3); border-color: rgba(16,185,129,0.5) !important; }
    40%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    45%  { box-shadow: 0 0 3px rgba(16,185,129,0.3); border-color: rgba(16,185,129,0.4) !important; }
    50%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    57%  { box-shadow: 0 0 3px rgba(16,185,129,0.2); border-color: rgba(16,185,129,0.3) !important; }
    63%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    72%  { box-shadow: 0 0 2px rgba(16,185,129,0.2); border-color: rgba(16,185,129,0.3) !important; }
    80%  { box-shadow: none;                         border-color: rgba(16,185,129,0.05) !important; }
    92%  { box-shadow: 0 0 2px rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2) !important; }
    100% { box-shadow: none;                         border-color: rgba(255,255,255,0.1) !important; }
}
</style>

<script>
function cardParallax() {
    return {
        rx: 0, ry: 0, mx: 0, my: 0, hovering: false,
        get cardStyle() {
            if (!this.hovering) return 'transform: rotateX(0deg) rotateY(0deg) translate(0px,0px) scale(1); transition: transform 0.5s ease;';
            const tx = this.mx * 6;
            const ty = this.my * 6;
            return `transform: rotateX(${this.rx}deg) rotateY(${this.ry}deg) translate(${tx}px,${ty}px) scale(1.05); transition: transform 0.1s ease; box-shadow: 0 12px 28px rgba(0,0,0,0.6);`;
        },
        layerStyle(depth) {
            if (!this.hovering) return 'transform: translate(0,0); transition: transform 0.5s ease;';
            const tx = this.mx * depth * 1.5;
            const ty = this.my * depth * 1.5;
            return `transform: translate(${tx}px, ${ty}px); transition: transform 0.1s ease;`;
        },
        move(e) {
            this.hovering = true;
            const r = e.currentTarget.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width  - 0.5;
            const y = (e.clientY - r.top)  / r.height - 0.5;
            this.ry =  x * 15;
            this.rx = -y * 15;
            this.mx =  x;
            this.my =  y;
        },
        leave() {
            this.hovering = false;
            this.rx = 0; this.ry = 0; this.mx = 0; this.my = 0;
        },
    };
}

function liveFeedH(initialMaxId) {
    return {
        items: [],
        connected: false,
        socket: null,
        tick: Date.now(),
        lastId: initialMaxId,

        isPersist() {
            return localStorage.getItem('livefeed_persist') === 'true';
        },

        loadSaved() {
            if (!this.isPersist()) return;
            try {
                const saved = JSON.parse(localStorage.getItem('livefeed_items') || '[]');
                if (Array.isArray(saved) && saved.length) {
                    const now = Date.now();
                    this.items = saved.map(i => ({
                        ...i,
                        isNew: i.isNew && i.addedAt && (now - i.addedAt) < 30000
                    }));
                    const maxSaved = Math.max(...saved.map(i => i.id || 0));
                    if (maxSaved > this.lastId) this.lastId = maxSaved;
                }
            } catch(e) {}
        },

        save() {
            if (!this.isPersist()) return;
            try {
                localStorage.setItem('livefeed_items', JSON.stringify(this.items.slice(0, 14)));
            } catch(e) {}
        },

        init() {
            this.loadSaved();
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
            const item = { ...data, isNew, addedAt: isNew ? Date.now() : (data.addedAt || null), created_at: data.created_at ?? new Date().toISOString() };
            const updated = [item, ...this.items];
            if (updated.length > 14) updated.splice(14);
            this.items = updated;
            this.save();
            if (isNew) {
                setTimeout(() => {
                    const found = this.items.find(i => i.id === item.id);
                    if (found) { found.isNew = false; this.save(); }
                }, 30000);
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

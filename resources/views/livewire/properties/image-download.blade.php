<?php

use App\Models\Property;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public int $id;
    public Property $property;

    public array $photos = [];
    public bool $loading = false;
    public ?string $error = null;
    public ?string $title = null;

    public function mount(int $id): void
    {
        if (!user_has_feature('properties_view')) {
            abort(403);
        }
        $this->id = $id;
        $this->property = Property::findOrFail($id);
        $this->fetchImages();
    }

    public function fetchImages(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->photos = [];
        $this->title = null;

        try {
            $url = $this->property->full_url;

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'az,en;q=0.9',
            ])->timeout(15)->get($url);

            if ($response->failed()) {
                $this->error = 'Səhifə yüklənə bilmədi';
                return;
            }

            $html = $response->body();

            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $jsonMatch);

            $imageUrls = [];

            if (!empty($jsonMatch[1])) {
                $json = json_decode($jsonMatch[1], true);

                if (isset($json['@graph'])) {
                    foreach ($json['@graph'] as $item) {
                        if (isset($item['@type']) && $item['@type'] === 'Product') {
                            $imageUrls = $item['image'] ?? [];
                            $this->title = $item['name'] ?? null;
                            break;
                        }
                    }
                } elseif (isset($json['@type']) && $json['@type'] === 'Product') {
                    $imageUrls = $json['image'] ?? [];
                    $this->title = $json['name'] ?? null;
                } elseif (isset($json[1]['@type']) && $json[1]['@type'] === 'Product') {
                    $imageUrls = $json[1]['image'] ?? [];
                    $this->title = $json[1]['name'] ?? null;
                }
            }

            $imageUrls = array_values(array_unique($imageUrls));

            if (empty($imageUrls)) {
                $this->error = 'Elanda şəkil tapılmadı';
                return;
            }

            preg_match('/\/items\/(\d+)/', $url, $idMatch);
            $itemId = $idMatch[1] ?? $this->id;

            $this->photos = array_map(function ($imgUrl, $i) use ($itemId) {
                $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                return [
                    'url'      => $imgUrl,
                    'filename' => $itemId . '_image_' . ($i + 1) . '.' . $ext,
                ];
            }, $imageUrls, array_keys($imageUrls));

        } catch (\Exception $e) {
            $this->error = 'Xəta: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }
}; ?>

<div
    x-data="{
        selected: [],
        init() {
            this.selected = Array.from({length: {{ count($photos) }}}, (_, i) => i);
            this.$watch('$wire.photos', photos => {
                this.selected = photos.map((_, i) => i);
            });
        },
        toggleAll() {
            if (this.selected.length === $wire.photos.length) {
                this.selected = [];
            } else {
                this.selected = $wire.photos.map((_, i) => i);
            }
        },
        toggle(i) {
            if (this.selected.includes(i)) {
                this.selected = this.selected.filter(x => x !== i);
            } else {
                this.selected.push(i);
            }
        },
        async downloadSelected() {
            if (this.selected.length === 0) return;
            const delay = ms => new Promise(r => setTimeout(r, ms));
            for (let i = 0; i < this.selected.length; i++) {
                const idx = this.selected[i];
                const p = $wire.photos[idx];
                const a = document.createElement('a');
                a.href = '/api/proxy-image?url=' + encodeURIComponent(p.url) + '&filename=' + encodeURIComponent(p.filename);
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                if (i < this.selected.length - 1) await delay(1500);
            }
        }
    }"
    class="max-w-5xl mx-auto px-4 py-6"
>
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 dark:text-white">Şəkilləri yüklə</h1>
            <p class="text-xs text-zinc-400">{{ $property->location_full_name ?? '—' }}</p>
        </div>
        <div class="ml-auto">
            <a href="{{ $property->full_url }}" target="_blank"
               class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-indigo-500 transition-colors">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
                bina.az-da aç
            </a>
        </div>
    </div>

    {{-- Loading --}}
    @if($loading)
        <div class="flex flex-col items-center justify-center py-20 gap-3">
            <svg class="size-8 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-sm text-zinc-400">bina.az-dan şəkillər gətirilir...</p>
        </div>
    @endif

    {{-- Error --}}
    @if($error)
        <div class="flex flex-col items-center justify-center py-16 gap-4">
            <div class="flex items-center gap-2 text-red-500 text-sm">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                {{ $error }}
            </div>
            <button wire:click="fetchImages"
                    class="text-sm text-indigo-500 hover:text-indigo-700 font-medium transition-colors">
                Yenidən cəhd et
            </button>
        </div>
    @endif

    {{-- Results --}}
    @if(!empty($photos) && !$loading)
        {{-- Toolbar --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3 text-sm">
                <button @click="toggleAll()"
                        class="text-indigo-500 hover:text-indigo-700 font-medium transition-colors">
                    <span x-text="selected.length === {{ count($photos) }} ? 'Hamısını ləğv et' : 'Hamısını seç'"></span>
                </button>
                <span class="text-zinc-400">(<span x-text="selected.length"></span> / {{ count($photos) }} seçildi)</span>
                @if($title)
                    <span class="text-zinc-500 text-xs truncate max-w-xs">— {{ $title }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="fetchImages"
                        class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-600 transition-colors px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Yenilə
                </button>
                <button
                    @click="downloadSelected()"
                    :disabled="selected.length === 0"
                    :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                    class="flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Seçilənləri yüklə
                </button>
            </div>
        </div>

        {{-- Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($photos as $i => $photo)
                <div
                    @click="toggle({{ $i }})"
                    :class="selected.includes({{ $i }}) ? 'ring-2 ring-indigo-500' : 'ring-1 ring-zinc-200 dark:ring-zinc-700'"
                    class="relative rounded-xl overflow-hidden cursor-pointer group transition-all"
                    style="aspect-ratio:4/3;"
                >
                    <img src="{{ $photo['url'] }}" alt="Şəkil {{ $i + 1 }}"
                         class="w-full h-full object-cover"
                         loading="lazy">
                    <div class="absolute inset-0 transition-colors"
                         :class="selected.includes({{ $i }}) ? 'bg-indigo-500/20' : 'bg-transparent group-hover:bg-black/10'">
                    </div>
                    <div class="absolute top-2 left-2 size-5 rounded-full flex items-center justify-center transition-all"
                         :class="selected.includes({{ $i }}) ? 'bg-indigo-500' : 'bg-white/60 border border-zinc-300'">
                        <svg x-show="selected.includes({{ $i }})" class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </div>
                    <div class="absolute bottom-1.5 right-2 text-[10px] text-white/70 font-medium drop-shadow">{{ $i + 1 }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

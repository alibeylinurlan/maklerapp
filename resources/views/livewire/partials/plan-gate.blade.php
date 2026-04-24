<div class="flex min-h-[60vh] items-center justify-center">
    <div class="mx-auto max-w-md text-center">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/30">
            <flux:icon.lock-closed class="size-10 text-indigo-400" />
        </div>
        <h2 class="text-xl font-bold text-zinc-800 dark:text-white">{{ $pageTitle ?? 'Bu bölmə' }} bağlıdır</h2>
        <p class="mt-2 text-zinc-500 dark:text-zinc-400">
            Bu bölməni açmaq üçün <strong class="text-zinc-700 dark:text-zinc-200">{{ $planName ?? 'müvafiq tarif' }}</strong> tələb olunur.
        </p>
        <div class="mt-6">
            <a href="{{ route('pricing') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                <flux:icon.credit-card class="size-4" />
                Tariflərə bax
            </a>
        </div>
    </div>
</div>

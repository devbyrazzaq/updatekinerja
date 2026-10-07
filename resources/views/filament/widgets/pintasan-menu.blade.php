@php
    $pintasan = $this->pintasan();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-squares-2x2"
        heading="Pintasan Menu"
        description="Menu yang paling sering dibuka, mengikuti hak akses Anda."
    >
        @if ($pintasan === [])
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-lock-closed"
                    class="h-8 w-8 text-gray-400"
                />

                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Belum ada menu yang dapat diakses
                </span>

                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Hubungi administrator bila Anda memerlukan akses ke menu tertentu.
                </span>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($pintasan as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="flex items-start gap-3 rounded-xl p-3 ring-1 ring-gray-950/5 transition hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $item['label'] }}
                            </span>

                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['keterangan'] }}
                            </span>

                            @if ($item['grup'] !== null)
                                <span class="mt-1 block text-[0.6875rem] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    {{ $item['grup'] }}
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

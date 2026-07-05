<x-filament-panels::page>
    {{ $this->infolist }}

    @php $sections = $this->getPermissionSections(); @endphp

    @if ($sections)
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ($sections as $type)
                <x-filament::section
                    :heading="$type['heading']"
                    :description="$type['description']"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($type['groups'] as $group)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="mb-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    {{ $group['heading'] }}
                                </p>
                                @if ($group['description'])
                                    <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">{{ $group['description'] }}</p>
                                @endif
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($group['labels'] as $label)
                                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                                            {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                <x-filament::icon icon="heroicon-o-lock-open" class="h-10 w-10 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum ada hak akses yang diberikan</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Role ini belum memiliki permission apapun.</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

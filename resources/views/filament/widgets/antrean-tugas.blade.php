@php
    $kelompok = $this->kelompokTugas();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-inbox-stack"
        heading="Perlu Tindakan Anda"
        description="Berkas yang menunggu dikerjakan pada menu yang dapat Anda akses."
    >
        @if ($kelompok === [])
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-8 w-8 text-success-500"
                />

                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Tidak ada tugas yang menunggu
                </span>

                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Seluruh berkas pada menu yang dapat Anda akses sudah ditindaklanjuti.
                </span>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($kelompok as $bagian)
                    <div>
                        <div class="mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $bagian['judul'] }}
                            </span>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $bagian['keterangan'] }}
                            </p>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($bagian['baris'] as $baris)
                                <a
                                    href="{{ $baris['url'] }}"
                                    class="flex items-center gap-3 py-2.5 transition hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <x-filament::icon
                                        :icon="$baris['icon']"
                                        @class([
                                            'h-5 w-5 shrink-0',
                                            'text-danger-500' => $baris['warna'] === 'danger',
                                            'text-warning-500' => $baris['warna'] === 'warning',
                                            'text-info-500' => $baris['warna'] === 'info',
                                        ])
                                    />

                                    <div class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                                            {{ $baris['label'] }}
                                        </span>

                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $baris['keterangan'] }}
                                        </span>
                                    </div>

                                    <x-filament::badge :color="$baris['warna']">
                                        {{ number_format($baris['jumlah'], 0, ',', '.') }}
                                    </x-filament::badge>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

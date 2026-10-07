{{-- Keterangan periode dan tahun kerja yang sedang berjalan.
     Lihat App\Filament\Widgets\PeriodeBerjalanWidget.

     Isinya sengaja tipis: satu judul periode, rentangnya, lalu dua baris keterangan
     berlabel. Tidak ada kotak bertumpuk agar sebaris dengan kartu sisa waktu di
     sebelahnya. --}}
@php
    $periode = $this->periode();
    $tahunKerja = $this->tahunKerja();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-calendar-days"
        heading="Periode Berjalan"
        description="Rentang waktu yang sedang dipakai seluruh menu."
        class="h-full"
    >
        @if ($periode === null)
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-calendar"
                    class="h-8 w-8 text-gray-400"
                />

                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Belum ada periode berjalan
                </span>

                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Tetapkan tahun kerja berjalan pada Pengaturan Program Kerja agar periodenya terbaca.
                </span>
            </div>
        @else
            <div class="space-y-4">
                <div>
                    <p class="text-xl font-semibold tracking-[-0.02em] text-gray-950 dark:text-white">
                        {{ $periode->name }}
                    </p>

                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->rentangPeriode() ?? 'Rentang periode belum diisi' }}
                    </p>
                </div>

                <dl class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                    <div class="flex items-baseline justify-between gap-3 py-2">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Tahun Kerja</dt>
                        <dd class="min-w-0 text-right">
                            <span class="block truncate font-medium text-gray-950 dark:text-white">
                                {{ $tahunKerja?->name ?? 'Belum ditetapkan' }}
                            </span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->rentangTahunKerja() ?? 'Rentang belum diisi' }}
                            </span>
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-3 py-2">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Lama Periode</dt>
                        <dd class="truncate text-right font-medium text-gray-950 dark:text-white">
                            {{ $this->lamaPeriode() ?? 'Belum diketahui' }}
                        </dd>
                    </div>
                </dl>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

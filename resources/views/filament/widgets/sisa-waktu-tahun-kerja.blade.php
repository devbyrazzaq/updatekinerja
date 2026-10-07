{{-- Sisa waktu tahun kerja berjalan, sebagai acuan semata.
     Lihat App\Filament\Widgets\SisaWaktuTahunKerjaWidget.

     Satu angka besar, satu bilah kemajuan bertanda tanggal mulai dan berakhir, lalu
     satu kalimat penutup — sebaris tinggi dengan kartu periode di sebelahnya. --}}
@php
    $tahunKerja = $this->tahunKerja();
    $sisaHari = $this->sisaHari();
    $persentase = $this->persentaseBerjalan();
    $warna = $this->warna();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-clock"
        heading="Sisa Waktu Tahun Kerja"
        description="Perkiraan waktu tersisa sebagai acuan, bukan penutupan otomatis."
        class="h-full"
    >
        @if ($tahunKerja === null)
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-clock"
                    class="h-8 w-8 text-gray-400"
                />

                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Belum ada tahun kerja berjalan
                </span>

                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Tetapkan tahun kerja berjalan pada Pengaturan Program Kerja.
                </span>
            </div>
        @else
            <div class="space-y-4">
                <div>
                    <p @class([
                        'text-xl font-semibold tracking-[-0.02em]',
                        'text-gray-950 dark:text-white' => $warna === 'gray',
                        'text-success-600 dark:text-success-400' => $warna === 'success',
                        'text-warning-600 dark:text-warning-400' => $warna === 'warning',
                        'text-danger-600 dark:text-danger-400' => $warna === 'danger',
                    ])>
                        {{ $this->sisaWaktu() }}
                    </p>

                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $tahunKerja->name }} berakhir {{ $this->tanggalBerakhir() ?? 'pada tanggal yang belum diisi' }}
                    </p>
                </div>

                @if ($persentase !== null)
                    <div class="space-y-1.5">
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div
                                @class([
                                    'h-full rounded-full',
                                    'bg-gray-400' => $warna === 'gray',
                                    'bg-success-500' => $warna === 'success',
                                    'bg-warning-500' => $warna === 'warning',
                                    'bg-danger-500' => $warna === 'danger',
                                ])
                                style="width: {{ number_format($persentase, 2, '.', '') }}%"
                            ></div>
                        </div>

                        <div class="flex items-baseline justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ number_format($persentase, 0, ',', '.') }}% tahun kerja berjalan</span>
                            <span class="truncate">{{ $this->tanggalBerakhir() }}</span>
                        </div>
                    </div>
                @endif

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($sisaHari !== null && $sisaHari < 0)
                        Tanggal berakhir sudah terlampaui, namun tahun kerja masih dibuka. Penutupannya tetap dilakukan manual pada Pengaturan Program Kerja.
                    @else
                        Acuan pengingat saja — tahun kerja tetap ditutup manual pada Pengaturan Program Kerja.
                    @endif
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

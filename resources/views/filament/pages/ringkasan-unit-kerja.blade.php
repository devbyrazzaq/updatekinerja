@php
    $ringkasan = $this->ringkasan();
    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
    $persen = fn (?float $nilai): string => $nilai === null ? '-' : number_format($nilai, 1, ',', '.').'%';

    $kartu = [
        [
            'label' => 'Total Pagu Anggaran',
            'nilai' => $rupiah($ringkasan->pagu),
            'keterangan' => 'Seluruh unit kerja pada tahun kerja terpilih',
            'warna' => 'text-gray-950 dark:text-white',
        ],
        [
            'label' => 'Total Anggaran Terserap',
            'nilai' => $rupiah($ringkasan->terserap()),
            'keterangan' => $persen($ringkasan->persentasePenyerapan()).' dari total pagu',
            'warna' => 'text-success-600 dark:text-success-400',
        ],
        [
            'label' => 'Sisa Pagu Anggaran',
            'nilai' => $rupiah($ringkasan->sisaPagu()),
            'keterangan' => $ringkasan->komitmen > 0
                ? $rupiah($ringkasan->komitmen).' sudah diajukan, menunggu cair'
                : 'Belum ada anggaran yang menunggu pencairan',
            'warna' => $ringkasan->sisaPagu() < 0
                ? 'text-danger-600 dark:text-danger-400'
                : 'text-warning-600 dark:text-warning-400',
        ],
        [
            'label' => 'Rata-rata Capaian Target',
            'nilai' => $persen($ringkasan->capaian),
            'keterangan' => $ringkasan->jumlahProgramDiajukan.' dari '.$ringkasan->jumlahProgram.' program kerja dilaksanakan',
            'warna' => 'text-primary-600 dark:text-primary-400',
        ],
    ];
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    {{-- Grid telanjang tanpa kelas fi-section: kelas itu memberi latar & garis kartu
         sendiri, sehingga barisnya tampak terbungkus kotak besar. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kartu as $item)
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $item['label'] }}
                </span>

                <div class="mt-1 text-2xl font-semibold {{ $item['warna'] }}">
                    {{ $item['nilai'] }}
                </div>

                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                    {{ $item['keterangan'] }}
                </span>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>

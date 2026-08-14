@php
    $ringkasan = $this->ringkasan();
    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
    $persen = fn (?float $nilai): string => $nilai === null ? '-' : number_format($nilai, 1, ',', '.').'%';

    $penyerapan = $ringkasan->persentasePenyerapan();
    $komitmen = $ringkasan->pagu > 0 ? $ringkasan->komitmen / $ringkasan->pagu * 100 : 0;

    // Lebar batang dipangkas 100% agar tidak melimpah keluar kotak; angka
    // sesungguhnya tetap ditulis apa adanya di sebelahnya.
    $lebarTerserap = min($penyerapan ?? 0, 100);
    $lebarKomitmen = max(0, min($komitmen, 100 - $lebarTerserap));
@endphp

<x-filament-panels::page>
    {{-- Penyaring "Tampilkan Data" dirender bersama widget di atas konten (lihat
         HasFilterAboveWidgets), jadi tidak diulang di sini. --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Penyerapan Pagu Anggaran
            </span>

            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $rupiah($ringkasan->terserap()) }} dari {{ $rupiah($ringkasan->pagu) }}
            </span>
        </div>

        <div class="mt-3 flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full bg-primary-500" style="width: {{ $lebarTerserap }}%"></div>
            <div class="h-full bg-warning-400/60" style="width: {{ $lebarKomitmen }}%"></div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-primary-500"></span>
                Terserap {{ $persen($penyerapan) }}
            </span>

            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-warning-400/60"></span>
                Menunggu cair {{ $rupiah($ringkasan->komitmen) }}
            </span>

            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-gray-300 dark:bg-gray-700"></span>
                Sisa pagu {{ $rupiah($ringkasan->sisaPagu()) }}
            </span>
        </div>
    </div>

    @php
        $kartu = [
            [
                'label' => 'Program Kerja Dilaksanakan',
                'nilai' => $ringkasan->jumlahProgramDiajukan.' / '.$ringkasan->jumlahProgram,
                'keterangan' => $persen($ringkasan->persentasePelaksanaan()).' dari yang ditawarkan',
                'warna' => 'text-gray-950 dark:text-white',
            ],
            [
                'label' => 'Realisasi Selesai',
                'nilai' => $ringkasan->jumlahSelesai.' / '.$ringkasan->jumlahRealisasi,
                'keterangan' => $persen($ringkasan->persentasePenyelesaian()).' realisasi tuntas',
                'warna' => 'text-success-600 dark:text-success-400',
            ],
            [
                'label' => 'Rata-rata Capaian Target',
                'nilai' => $persen($ringkasan->capaian),
                'keterangan' => $ringkasan->capaian === null
                    ? 'Belum ada laporan realisasi yang disetujui'
                    : 'Menurut laporan realisasi yang disetujui',
                'warna' => 'text-primary-600 dark:text-primary-400',
            ],
        ];
    @endphp

    {{-- Grid telanjang tanpa kelas fi-section: kelas itu memberi latar & garis kartu
         sendiri, sehingga barisnya tampak terbungkus kotak besar. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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

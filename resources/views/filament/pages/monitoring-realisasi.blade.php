@php
    $ringkasan = $this->ringkasan();
    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
    $persen = fn (?float $nilai): string => $nilai === null ? '-' : number_format($nilai, 1, ',', '.').'%';

    // Batangnya membandingkan aliran anggaran terhadap seluruh yang sudah disetujui:
    // bagian yang benar-benar cair, lalu bagian yang masih menunggu pencairan.
    $disetujui = $ringkasan->disetujui;
    $porsi = fn (float $nominal): float => $disetujui > 0 ? min($nominal / $disetujui * 100, 100) : 0;

    $lebarCair = $porsi($ringkasan->dicairkan);
    $lebarMenunggu = max(0, min($porsi($ringkasan->menungguCair()), 100 - $lebarCair));
@endphp

<x-filament-panels::page>
    {{-- Penyaring "Tampilkan Data" dirender bersama widget di atas konten (lihat
         HasFilterAboveWidgets), jadi tidak diulang di sini. --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Aliran Anggaran Realisasi
            </span>

            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $rupiah($ringkasan->dicairkan) }} cair dari {{ $rupiah($disetujui) }} disetujui
            </span>
        </div>

        <div class="mt-3 flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div class="h-full bg-primary-500" style="width: {{ $lebarCair }}%"></div>
            <div class="h-full bg-warning-400/60" style="width: {{ $lebarMenunggu }}%"></div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-primary-500"></span>
                Dicairkan {{ $rupiah($ringkasan->dicairkan) }}
            </span>

            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-warning-400/60"></span>
                Menunggu cair {{ $rupiah($ringkasan->menungguCair()) }}
            </span>

            <span class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-success-500"></span>
                Dilaporkan {{ $rupiah($ringkasan->dilaporkan) }}
            </span>
        </div>
    </div>

    @php
        $kartu = [
            [
                'label' => 'Realisasi Berjalan',
                'nilai' => $ringkasan->berjalan.' / '.$ringkasan->jumlah,
                'keterangan' => $ringkasan->berjalan > 0
                    ? 'Masih menunggu penyelesaian'
                    : 'Tidak ada realisasi yang menggantung',
                'warna' => 'text-warning-600 dark:text-warning-400',
            ],
            [
                'label' => 'Realisasi Selesai',
                'nilai' => $ringkasan->selesai.' / '.$ringkasan->jumlah,
                'keterangan' => $persen($ringkasan->persentaseSelesai()).' realisasi tuntas',
                'warna' => 'text-success-600 dark:text-success-400',
            ],
            [
                'label' => 'Laporan Belum Tuntas',
                'nilai' => (string) $ringkasan->belumDipertanggungjawabkan(),
                'keterangan' => 'Anggaran sudah cair, laporannya belum disetujui',
                'warna' => 'text-gray-950 dark:text-white',
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
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

@php
    $ringkasan = $this->ringkasan();
    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    @php
        $barisKartu = [
            // Baris 1: nominal yang tersedia — pagu yang ditetapkan & pemasukan yang tercatat.
            [
                ['label' => 'Total Pagu Anggaran', 'nominal' => $ringkasan['pagu'], 'keterangan' => 'Ditetapkan untuk seluruh unit kerja', 'warna' => 'text-gray-950 dark:text-white'],
                ['label' => 'Total Pemasukan', 'nominal' => $ringkasan['pemasukan'], 'keterangan' => 'Tercatat, belum menambah plafon', 'warna' => 'text-info-600 dark:text-info-400'],
            ],
            // Baris 2: pergerakan buku & sisanya.
            [
                ['label' => 'Total Kredit', 'nominal' => $ringkasan['kredit'], 'keterangan' => 'Pagu & pengembalian sisa anggaran', 'warna' => 'text-success-600 dark:text-success-400'],
                ['label' => 'Total Debit', 'nominal' => $ringkasan['debit'], 'keterangan' => 'Anggaran dicairkan & kekurangan dilunasi', 'warna' => 'text-danger-600 dark:text-danger-400'],
                ['label' => 'Sisa Pagu Anggaran', 'nominal' => $ringkasan['saldo'], 'keterangan' => 'Total pagu dikurangi anggaran yang cair', 'warna' => $ringkasan['saldo'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-primary-600 dark:text-primary-400'],
            ],
        ];
    @endphp

    @foreach ($barisKartu as $baris)
        {{-- Grid telanjang tanpa kelas fi-section: kelas itu memberi latar & garis kartu
             sendiri, sehingga barisnya tampak terbungkus kotak besar. --}}
        <div @class([
            'grid gap-4 sm:grid-cols-2',
            'lg:grid-cols-3' => count($baris) === 3,
        ])>
            @foreach ($baris as $kartu)
                <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $kartu['label'] }}
                    </span>

                    <div class="mt-1 text-2xl font-semibold {{ $kartu['warna'] }}">
                        {{ $rupiah($kartu['nominal']) }}
                    </div>

                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                        {{ $kartu['keterangan'] }}
                    </span>
                </div>
            @endforeach
        </div>
    @endforeach

    {{ $this->table }}
</x-filament-panels::page>

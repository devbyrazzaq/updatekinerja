@php
    $kolom = $this->kolom();
    $metrik = $this->metrik();
    $total = $this->total();

    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
    $persen = fn (?float $nilai): string => $nilai === null ? '-' : number_format($nilai, 1, ',', '.').'%';

    $format = fn (mixed $nilai, string $format): string => match ($format) {
        'rupiah' => $rupiah((float) ($nilai ?? 0)),
        'persen' => $persen($nilai === null ? null : (float) $nilai),
        default => number_format((int) ($nilai ?? 0), 0, ',', '.'),
    };

    // Metrik yang "makin tinggi makin baik" — hanya ini yang pantas ditandai sebagai
    // unggulan. Sisa pagu sengaja tidak ikut: besar kecilnya bukan prestasi.
    $metrikUnggulan = ['terserap', 'persentase_penyerapan', 'jumlah_program_diajukan', 'jumlah_selesai', 'capaian'];

    $totalBaris = $total->toArray();

    // Kolom metrik dibekukan saat matriks digulir mendatar. Latarnya harus pekat
    // (bukan tint tembus pandang) supaya angka yang lewat di belakangnya tidak
    // membayang.
    $kolomBeku = 'sticky start-0 z-10 border-e border-gray-200 dark:border-white/10';
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    {{-- Memakai anatomi tabel Filament (fi-ta-*) agar matriks ini tampil sama persis
         dengan tabel lain di aplikasi. fi-ta-main yang ber-min-w-0 itulah yang membuat
         matriks lebar menggulir di dalam kartunya sendiri, bukan melebarkan halaman. --}}
    <div class="fi-ta-ctn">
        <div class="fi-ta-main">
            <div class="fi-ta-header">
                <h3 class="fi-ta-header-heading">Matriks Perbandingan</h3>

                <p class="fi-ta-header-description">
                    {{ $this->mode === \App\Filament\Pages\PerbandinganMonitoring::MODE_TAHUN
                        ? 'Angka tiap tahun kerja disandingkan pada cakupan unit kerja yang sama.'
                        : 'Angka tiap unit kerja disandingkan pada tahun kerja yang sama.' }}
                </p>
            </div>

            @if (count($kolom) === 0)
                <div class="fi-ta-empty-state">
                    <div class="fi-ta-empty-state-content">
                        <div class="fi-ta-empty-state-icon-bg">
                            <x-filament::icon
                                icon="heroicon-o-scale"
                                class="size-6"
                            />
                        </div>

                        <h4 class="fi-ta-empty-state-heading">
                            Belum ada pembanding yang dipilih
                        </h4>

                        <p class="fi-ta-empty-state-description">
                            Pilih dua {{ $this->mode === \App\Filament\Pages\PerbandinganMonitoring::MODE_TAHUN ? 'tahun kerja' : 'unit kerja' }} atau lebih pada penyaring di atas untuk mulai membandingkan.
                        </p>
                    </div>
                </div>
            @else
                <div class="fi-ta-content-ctn">
                    <div class="fi-ta-content">
                        <table class="fi-ta-table">
                            <thead>
                                <tr>
                                    <th class="fi-ta-header-cell bg-gray-50 dark:bg-gray-800 {{ $kolomBeku }}">
                                        Metrik
                                    </th>

                                    @foreach ($kolom as $pembanding)
                                        <th class="fi-ta-header-cell fi-align-end">
                                            {{ $pembanding['label'] }}
                                        </th>
                                    @endforeach

                                    <th class="fi-ta-header-cell fi-align-end bg-gray-100 dark:bg-white/10">
                                        Total
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($metrik as $baris)
                                    @php
                                        $unggulan = in_array($baris['kunci'], $metrikUnggulan, true)
                                            ? $this->kolomTerbaik($baris['kunci'])
                                            : null;
                                    @endphp

                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell bg-white dark:bg-gray-900 {{ $kolomBeku }}">
                                            <div class="fi-ta-text whitespace-normal">
                                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                                    {{ $baris['label'] }}
                                                </span>

                                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $baris['keterangan'] }}
                                                </span>
                                            </div>
                                        </td>

                                        @foreach ($kolom as $urutan => $pembanding)
                                            <td class="fi-ta-cell fi-align-end">
                                                <div @class([
                                                    'fi-ta-text text-sm tabular-nums',
                                                    'font-semibold text-success-600 dark:text-success-400' => $unggulan === $urutan,
                                                    'text-gray-700 dark:text-gray-300' => $unggulan !== $urutan,
                                                ])>
                                                    {{ $format($pembanding[$baris['kunci']] ?? null, $baris['format']) }}
                                                </div>
                                            </td>
                                        @endforeach

                                        <td class="fi-ta-cell fi-align-end bg-gray-50 dark:bg-white/5">
                                            <div class="fi-ta-text text-sm font-semibold tabular-nums text-gray-950 dark:text-white">
                                                {{ $format($totalBaris[$baris['kunci']] ?? null, $baris['format']) }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <p class="border-t border-gray-200 px-4 py-3 text-xs text-gray-500 sm:px-6 dark:border-white/10 dark:text-gray-400">
                Angka bercetak tebal berwarna hijau adalah pembanding tertinggi pada metrik tersebut. Kolom Total menjumlahkan seluruh pembanding; capaian target dirata-rata dengan bobot jumlah realisasi yang selesai.
            </p>
        </div>
    </div>
</x-filament-panels::page>

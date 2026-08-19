<?php

namespace App\Filament\Pages\Concerns;

use Closure;
use Filament\Forms\Components\Select;

/**
 * Pertanyaan cakupan unit kerja yang muncul sebelum laporan PDF diunduh.
 *
 * Laporan PDF dicetak untuk dibagikan — kerap bukan untuk cakupan yang kebetulan
 * sedang dibaca di layar — sehingga cakupannya ditanyakan sekali saat mengunduh,
 * tanpa mengubah penyaring halaman. Ekspor spreadsheet tetap mengikuti penyaring
 * halaman apa adanya, karena berkasnya adalah salinan tabel yang terlihat.
 *
 * Halaman yang memakai trait ini wajib punya `unitKerjaOptions()` berisi unit kerja
 * yang boleh dibaca pengguna, sehingga pilihannya tidak pernah melampaui pembatasan
 * data yang berlaku.
 */
trait MemilihCakupanLaporan
{
    /**
     * Isi modal laporan: satu unit kerja, atau dikosongkan untuk seluruh unit kerja.
     *
     * @param  Closure|int|null  $bawaan  Pilihan awal; biasanya penyaring halaman.
     * @return array<int, Select>
     */
    protected function skemaCakupanLaporan(Closure|int|null $bawaan = null): array
    {
        return [
            Select::make('unitKerjaId')
                ->label('Cakupan Unit Kerja')
                ->options($this->unitKerjaOptions())
                ->default($bawaan)
                ->placeholder('Seluruh Unit Kerja')
                ->searchable()
                ->native(false)
                ->helperText('Pilih satu unit kerja, atau kosongkan untuk melaporkan seluruh unit kerja yang boleh diakses.'),
        ];
    }

    /**
     * Unit kerja yang dipilih pada modal; null berarti seluruh unit kerja.
     *
     * @param  array<string, mixed>  $data
     */
    protected function cakupanUnitKerja(array $data): ?int
    {
        return filled($data['unitKerjaId'] ?? null) ? (int) $data['unitKerjaId'] : null;
    }

    /**
     * @return array<int, string>
     */
    abstract protected function unitKerjaOptions(): array;
}

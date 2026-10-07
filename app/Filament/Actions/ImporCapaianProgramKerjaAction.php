<?php

namespace App\Filament\Actions;

use App\Enums\EnumSumberReferensiProgramKerja;
use App\Imports\CapaianProgramKerjasImport;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi mengimpor capaian program kerja secara massal dari berkas .xlsx/.csv,
 * padanan berkas dari {@see CatatCapaianProgramKerjaAction}.
 *
 * Cakupan tahun kerja dan unit kerja yang boleh dirujuk berkas diberikan halaman
 * pemanggil lewat {@see ExcelImportAction::importerContext()} — nilainya dipakai baik
 * saat menyusun lembar referensi pada berkas template maupun saat memvalidasi berkas
 * yang diunggah, sehingga keduanya tidak mungkin berbeda.
 */
class ImporCapaianProgramKerjaAction extends ExcelImportAction
{
    public static function getDefaultName(): ?string
    {
        return 'imporCapaian';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer(CapaianProgramKerjasImport::class)
            ->label('Impor Capaian')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('primary')
            ->modalHeading('Impor Capaian Program Kerja')
            ->modalDescription('Pilih dahulu asal lembar referensinya, lalu unduh templatenya — berkasnya sudah berisi lembar petunjuk pengisian dan lembar referensi kode program kerja pada tahun kerja yang sedang dipantau. Isi satu baris untuk satu capaian, lalu unggah kembali. Seluruh baris diperiksa lebih dahulu; bila ada satu saja yang keliru, tidak ada yang tersimpan.')
            ->modalSubmitActionLabel('Impor Capaian')
            ->modalWidth(Width::TwoExtraLarge)
            ->formFields([
                Select::make('sumber_referensi')
                    ->label('Sumber Lembar Referensi')
                    ->options(EnumSumberReferensiProgramKerja::class)
                    ->default(EnumSumberReferensiProgramKerja::bawaan()->value)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    // Nilainya dibaca tombol "Unduh Template" saat ditekan, jadi harus
                    // sudah tersimpan lebih dahulu.
                    ->live()
                    ->helperText(fn (mixed $state): string => EnumSumberReferensiProgramKerja::dariNilai($state)->keterangan())
                    ->required(),
            ]);
    }
}

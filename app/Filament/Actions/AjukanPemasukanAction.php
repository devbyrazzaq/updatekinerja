<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Forms\Components\CatatanRevisi;
use App\Models\Pemasukan;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi mengajukan pemasukan unit untuk diverifikasi, dipakai bersama oleh tabel
 * pemasukan dan header halaman detail.
 *
 * Pemasukan berstatus draf diajukan untuk pertama kali, sedangkan pemasukan yang
 * diminta revisi diperbaiki lewat aksi yang sama ("Perbaiki") lalu diteruskan
 * kembali ke tahap tempat revisi diminta — bukan mengulang dari Rektor.
 */
class AjukanPemasukanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'ajukanPemasukan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Pemasukan $record): string => static::adalahPerbaikan($record) ? 'Perbaiki' : 'Ajukan')
            ->icon(fn (Pemasukan $record): string => static::adalahPerbaikan($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->visible(fn (Pemasukan $record): bool => $record->dapatDiajukan())
            ->modalHeading(fn (Pemasukan $record): string => static::adalahPerbaikan($record) ? 'Ajukan Ulang Pemasukan' : 'Ajukan Pemasukan')
            ->modalDescription(fn (Pemasukan $record): string => static::adalahPerbaikan($record)
                ? 'Pastikan data pemasukan sudah diperbaiki sesuai catatan revisi. Pemasukan akan dikirim kembali ke tahap yang meminta revisi.'
                : 'Pemasukan akan dikirim untuk verifikasi Rektor, dilanjutkan Wakil Rektor dan Biro Keuangan.')
            ->modalSubmitActionLabel(fn (Pemasukan $record): string => static::adalahPerbaikan($record) ? 'Kirim Ulang' : 'Ajukan')
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalIconColor('info')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(fn (Pemasukan $record): array => CatatanRevisi::komponen($record))
            ->action(function (Pemasukan $record): void {
                if (! $record->dapatDiajukan()) {
                    Notification::make()
                        ->title('Pemasukan tidak dapat diajukan')
                        ->body('Pemasukan ini sudah berada dalam proses verifikasi atau sudah selesai.')
                        ->danger()
                        ->send();

                    return;
                }

                $diajukanKembali = static::adalahPerbaikan($record);
                $tujuan = $record->statusTujuanPengajuan();

                $record->update([
                    'status' => $tujuan,
                    'catatan_verifikasi' => null,
                    // Jaring pengaman untuk record hasil impor/seeder yang tidak lewat
                    // halaman Create sehingga pencatatnya belum terisi.
                    ...($record->user_id === null ? ['user_id' => auth()->id()] : []),
                ]);

                $record->catatLog($tujuan, auth()->id(), null, diajukanKembali: $diajukanKembali);

                app(NotifikasiVerifikasi::class)->pemasukanDiajukan($record, $diajukanKembali);

                Notification::make()->title('Pemasukan berhasil diajukan')->success()->send();
            });
    }

    /**
     * Pemasukan yang dikembalikan verifikator, sehingga aksi ini menjadi perbaikan
     * alih-alih pengajuan pertama.
     */
    protected static function adalahPerbaikan(Pemasukan $record): bool
    {
        return $record->status === EnumStatusPemasukan::Revisi;
    }
}

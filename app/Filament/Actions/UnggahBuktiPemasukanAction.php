<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Models\Pemasukan;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Langkah penutup alur pemasukan: setelah Biro Keuangan menyetujui, unit kerja
 * mengunggah bukti tanda terima. Unggahan inilah yang mengesahkan pemasukan menjadi
 * Valid — tidak ada tahap verifikasi bukti tersendiri.
 *
 * Batas jumlah dan ukuran berkas dibaca dari Pengaturan Sistem agar dapat diubah
 * tanpa deploy ulang.
 */
class UnggahBuktiPemasukanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'unggahBuktiPemasukan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Unggah Bukti Tanda Terima')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->visible(fn (Pemasukan $record): bool => PemasukanResource::currentUserCanAbility('update')
                && $record->status === EnumStatusPemasukan::MenungguBukti)
            ->modalHeading('Unggah Bukti Tanda Terima')
            ->modalDescription('Pemasukan dinyatakan valid dan tercatat pada buku anggaran begitu bukti tanda terima diunggah.')
            ->modalSubmitActionLabel('Unggah & Sahkan')
            ->modalIcon('heroicon-o-arrow-up-tray')
            ->modalIconColor('success')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(fn (): array => [
                FileUpload::make('bukti_path')
                    ->label('Bukti Tanda Terima')
                    ->helperText('Berkas PDF atau gambar, maksimal '.Setting::maksUkuranBuktiPemasukanKb() / 1024 .' MB per berkas. Minimal 1, maksimal '.Setting::maksBuktiPemasukan().' berkas.')
                    ->required()
                    ->multiple()
                    ->minFiles(1)
                    ->maxFiles(Setting::maksBuktiPemasukan())
                    ->reorderable()
                    ->appendFiles()
                    ->directory('bukti-pemasukan')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(Setting::maksUkuranBuktiPemasukanKb())
                    ->storeFileNamesIn('bukti_original_names')
                    ->downloadable()
                    ->openable(),
            ])
            ->action(function (array $data, Pemasukan $record): void {
                if ($record->status !== EnumStatusPemasukan::MenungguBukti) {
                    Notification::make()
                        ->title('Bukti tidak dapat diunggah')
                        ->body('Pemasukan ini belum disetujui Biro Keuangan atau sudah disahkan sebelumnya.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'bukti_path' => $data['bukti_path'],
                    'bukti_original_names' => $data['bukti_original_names'] ?? null,
                    'status' => EnumStatusPemasukan::Valid,
                    'bukti_diserahkan_at' => now(),
                    'divalidasi_at' => now(),
                ]);

                $record->catatLog(EnumStatusPemasukan::Valid, auth()->id());

                Notification::make()->title('Pemasukan dinyatakan valid')->success()->send();
            });
    }
}

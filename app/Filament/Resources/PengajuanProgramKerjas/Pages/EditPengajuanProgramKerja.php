<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Pages;

use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPengajuanProgramKerja extends EditRecord
{
    protected static string $resource = PengajuanProgramKerjaResource::class;

    /**
     * Kolom yang perubahannya dicatat pada riwayat pengajuan.
     *
     * @var array<int, string>
     */
    protected array $kolomTerlacak = [
        'alokasi_anggaran',
        'deskripsi_kegiatan',
        'estimasi_mulai',
        'estimasi_selesai',
        'unit_kerja_id',
        'penawaran_program_kerja_id',
    ];

    /**
     * Menyimpan perubahan sekaligus mencatat kolom terlacak yang berubah ke log.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);

        $perubahan = [];

        foreach ($this->kolomTerlacak as $kolom) {
            if ($record->isDirty($kolom)) {
                $perubahan[$kolom] = [
                    'lama' => $record->getOriginal($kolom),
                    'baru' => $record->getAttribute($kolom),
                ];
            }
        }

        $record = parent::handleRecordUpdate($record, $data);

        if ($perubahan !== []) {
            $record->catatPerubahan($perubahan, auth()->id());
        }

        return $record;
    }

    public function getBreadcrumb(): string
    {
        return 'Ubah';
    }

    /**
     * @return array<int, AuthorizedViewAction|CaptchaDeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedViewAction::make()
                ->icon('heroicon-o-eye')
                ->label('Masuk Ke View Mode'),
            CaptchaDeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return parent::getSavedNotification()?->title('Data Pengajuan Program Kerja berhasil diperbarui');
    }
}

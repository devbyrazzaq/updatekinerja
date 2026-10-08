<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Pages;

use App\Enums\EnumStatusPengajuan;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Models\PengajuanProgramKerja;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPengajuanProgramKerja extends ViewRecord
{
    protected static string $resource = PengajuanProgramKerjaResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pengajuan Program Kerja';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl() => 'Data Pengajuan Program Kerja',
            '' => 'Detail',
        ];
    }

    /**
     * @return array<int, Action|AuthorizedEditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getAjukanAction(),
            $this->getAjukanKembaliAction(),
            $this->getAjukanRealisasiAction(),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }

    /**
     * Setelah pengajuan diterima, unit kerja dapat langsung mengajukan realisasi
     * terhadap pengajuan ini. Mengarahkan ke form realisasi dengan pengajuan induk
     * sudah terisi.
     *
     * Pengajuan tahun yang masih direncanakan sengaja tidak menawarkan aksi ini:
     * realisasi baru hanya boleh lahir dari tahun yang benar-benar berjalan.
     */
    protected function getAjukanRealisasiAction(): Action
    {
        return Action::make('ajukanRealisasi')
            ->label('Ajukan Realisasi')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->visible(fn (PengajuanProgramKerja $record): bool => $record->status === EnumStatusPengajuan::Diterima
                && ($record->penawaranProgramKerja?->tahunKerja?->status?->bolehRealisasiBaru() ?? false)
                && RealisasiProgramKerjaResource::canCreate())
            ->url(fn (PengajuanProgramKerja $record): string => RealisasiProgramKerjaResource::getUrl('create', ['pengajuan' => $record->getRouteKey()]));
    }

    /**
     * Mengajukan kembali pengajuan yang diminta revisi setelah unit kerja
     * memperbaikinya, sehingga masuk lagi ke tahap verifikasi.
     */
    protected function getAjukanKembaliAction(): Action
    {
        return Action::make('ajukanKembali')
            ->label('Ajukan Kembali')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Ajukan Kembali Pengajuan')
            ->modalDescription('Pengajuan yang telah diperbaiki akan diteruskan kembali untuk verifikasi.')
            ->modalSubmitActionLabel('Ya, Ajukan Kembali')
            ->visible(fn (PengajuanProgramKerja $record): bool => static::getResource()::currentUserCanAbility('update')
                && $record->status === EnumStatusPengajuan::Revisi)
            ->action(function (PengajuanProgramKerja $record): void {
                $record->update(['status' => EnumStatusPengajuan::Diajukan]);

                $record->catatLog(EnumStatusPengajuan::Diajukan, auth()->id());

                Notification::make()->title('Pengajuan berhasil diajukan kembali')->success()->send();
            });
    }

    /**
     * Mengajukan pengajuan yang masih berstatus draf agar masuk tahap verifikasi.
     */
    protected function getAjukanAction(): Action
    {
        return Action::make('ajukan')
            ->label('Ajukan')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Ajukan Pengajuan')
            ->modalDescription('Pengajuan akan diteruskan untuk verifikasi dan tidak dapat diubah statusnya kembali menjadi draf.')
            ->modalSubmitActionLabel('Ya, Ajukan')
            ->visible(fn (PengajuanProgramKerja $record): bool => static::getResource()::currentUserCanAbility('update')
                && $record->status === EnumStatusPengajuan::Draft)
            ->action(function (PengajuanProgramKerja $record): void {
                $record->update(['status' => EnumStatusPengajuan::Diajukan]);

                $record->catatLog(EnumStatusPengajuan::Diajukan, auth()->id());

                Notification::make()->title('Pengajuan berhasil diajukan')->success()->send();
            });
    }
}

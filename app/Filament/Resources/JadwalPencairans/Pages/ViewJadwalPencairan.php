<?php

namespace App\Filament\Resources\JadwalPencairans\Pages;

use App\Exports\JadwalPencairanExport;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\JadwalPencairans\Tables\JadwalPencairansTable;
use App\Models\JadwalPencairan;
use App\Models\Setting;
use App\Reports\LaporanPencairanReport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ViewJadwalPencairan extends ViewRecord
{
    protected static string $resource = JadwalPencairanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            JadwalPencairanResource::getUrl() => 'Jadwal Pencairan',
            '' => 'Detail',
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            JadwalPencairansTable::cairkanAction(),
            ActionGroup::make([
                ExcelExportAction::make()
                    ->label('Ekspor Excel')
                    ->permission(static::getResource()::getPermissionName('export'))
                    ->action(fn (): BinaryFileResponse => (new JadwalPencairanExport($this->jadwal()))->download()),
                $this->laporanPencairanAction(),
            ])
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }

    /**
     * Unduh Laporan Pencairan sebagai PDF. Tanggal pada blok tanda tangan ditanyakan
     * lebih dulu karena laporan kerap dicetak untuk tanggal penyerahan berkas, bukan
     * tanggal cetaknya; identitas penanda tangannya diambil dari Pengaturan Sistem.
     */
    protected function laporanPencairanAction(): Action
    {
        return PdfReportAction::make()
            ->label('Laporan PDF')
            ->permission(static::getResource()::getPermissionName('report'))
            ->modalHeading('Unduh Laporan Pencairan')
            ->modalDescription('Tanggal berikut tercetak di atas blok tanda tangan laporan. Jabatan, nama pimpinan, dan nomor karyawannya mengikuti Pengaturan Sistem.')
            ->modalSubmitActionLabel('Unduh Laporan')
            ->modalIcon(Heroicon::DocumentArrowDown)
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal Laporan')
                    ->required()
                    ->default(now())
                    ->helperText($this->keteranganPenandatangan()),
            ])
            ->action(fn (array $data): BinaryFileResponse => (new LaporanPencairanReport(
                $this->jadwal(),
                Carbon::parse($data['tanggal']),
            ))->download());
    }

    /**
     * Pengingat siapa yang akan tercetak sebagai penanda tangan, sekaligus penunjuk
     * tempat mengubahnya bila identitas pimpinannya belum diisi.
     */
    protected function keteranganPenandatangan(): string
    {
        $nama = Setting::penandatanganNama();
        $jabatan = Setting::penandatanganJabatan();

        if ($nama === '' || $jabatan === '') {
            return 'Nama dan jabatan pimpinan penanda tangan belum lengkap — isi di Pengaturan Sistem agar tercetak pada laporan.';
        }

        return 'Ditandatangani oleh '.$nama.' sebagai '.$jabatan.'.';
    }

    protected function jadwal(): JadwalPencairan
    {
        /** @var JadwalPencairan $jadwal */
        $jadwal = $this->getRecord();

        return $jadwal;
    }
}

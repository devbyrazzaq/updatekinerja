<?php

namespace App\Filament\Resources\VerifikasiLaporans\Tables;

use App\Filament\Actions\MediaAction;
use App\Filament\Actions\RevisiLaporanRealisasiAction;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerifikasiLaporansTable
{
    use HasVerificationTableFilters;

    /**
     * @param  array<int, int>|null  $tahunKerjaIds  Pilihan filter tahun kerja; bawaannya tahun berjalan.
     */
    public static function configure(Table $table, ?array $tahunKerjaIds = null): Table
    {
        return $table
            ->emptyStateHeading('Tidak ada laporan yang menunggu verifikasi')
            ->emptyStateDescription('Laporan realisasi yang diunggah unit kerja akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-document-check')
            ->columns([
                TextColumn::make('name')->label('Kegiatan')->searchable()->wrap(),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')->label('Unit Kerja')->searchable(),
                TextColumn::make('anggaran_digunakan')->label('Digunakan')->money('IDR')->sortable(),
                TextColumn::make('status_anggaran')->label('Status Anggaran')->badge()->placeholder('-'),
                TextColumn::make('persentase_ketercapaian')
                    ->label('Ketercapaian')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : "{$state}%")
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('laporan_diserahkan_at')->label('Laporan Diserahkan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                    ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
            ])
            ->filters([
                static::tahunKerjaFilter('pengajuanProgramKerja.penawaranProgramKerja', $tahunKerjaIds ?? KonteksProgramKerja::tahunPelaksanaanIds()),
                static::unitKerjaFilter('pengajuanProgramKerja'),
                // Yang diajukan pada tahap ini adalah laporannya, bukan realisasinya,
                // sehingga rentang waktu mengikuti tanggal laporan diserahkan.
                static::waktuPengajuanFilter('laporan_diserahkan_at', 'Waktu Pengajuan Laporan'),
            ])
            ->recordActions([
                MediaAction::make('lihatProposal')
                    ->label('Lihat Proposal')
                    ->color('gray')
                    ->path('proposal_path'),
                MediaAction::make('lihatLaporan')
                    ->label('Lihat Laporan')
                    ->color('gray')
                    ->path('laporan_path'),
                // Verifikasi dilakukan dari modal detail: verifikator meninjau isi laporan
                // yang sama persis dengan halaman detail, lalu memutuskan lewat tombol
                // Terima/Revisi pada footer modal tersebut.
                ViewAction::make()
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (RealisasiProgramKerja $record): string => $record->name ?? 'Detail Realisasi Program Kerja')
                    ->modalDescription('Tinjau laporan realisasi sebelum memutuskan.')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalCancelActionLabel('Tutup')
                    ->extraModalFooterActions([
                        TerimaLaporanRealisasiAction::make(),
                        RevisiLaporanRealisasiAction::make(),
                    ]),
            ]);
    }
}

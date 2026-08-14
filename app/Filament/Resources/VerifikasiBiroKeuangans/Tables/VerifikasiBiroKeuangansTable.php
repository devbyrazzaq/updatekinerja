<?php

namespace App\Filament\Resources\VerifikasiBiroKeuangans\Tables;

use App\Filament\Actions\MediaAction;
use App\Filament\Actions\ProsesPencairanAction;
use App\Filament\Actions\TandaiDicairkanAction;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tabel tahap Biro Keuangan. Alur di halaman ini berjalan dua langkah:
 *
 * 1. `prosesPencairan` — realisasi berstatus Verifikasi Biro Keuangan dimasukkan ke
 *    salah satu Jadwal Pencairan (dibuat langsung dari modal bila belum ada),
 *    statusnya menjadi "Menunggu Anggaran Diberikan".
 * 2. `tandaiDicairkan` — anggaran dinyatakan sudah diberikan ke unit kerja beserta
 *    cara pembayarannya (transfer ke rekening atau tunai), statusnya menjadi
 *    "Menunggu Laporan Realisasi" sehingga unit kerja diminta melaporkan
 *    kegiatannya. Record pindah dari tab "Perlu Diproses" ke tab "Sudah Diproses".
 *
 * Kedua aksi tersedia di baris tabel maupun di footer modal detail, agar Biro
 * Keuangan tidak perlu berpindah halaman. Pencairan satu jadwal sekaligus
 * dilakukan dari menu Jadwal Pencairan.
 */
class VerifikasiBiroKeuangansTable
{
    use HasVerificationTableFilters;

    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Tidak ada realisasi untuk diproses')
            ->emptyStateDescription('Realisasi yang disetujui Wakil Rektor akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('name')->label('Kegiatan')->searchable()->wrap(),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')->label('Unit Kerja')->searchable(),
                TextColumn::make('nominal_disetujui')->label('Nominal Disetujui')->money('IDR')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('jadwalPencairan.name')->label('Jadwal Pencairan')->placeholder('-')->description(fn (RealisasiProgramKerja $record): ?string => $record->jadwalPencairan?->tanggal_pencairan?->locale('id')->translatedFormat('d F Y')),
                TextColumn::make('metode_pembayaran')
                    ->label('Pembayaran')
                    ->badge()
                    ->placeholder('-')
                    ->description(fn (RealisasiProgramKerja $record): ?string => $record->rekeningBank?->label()),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d F Y H:i')->sortable(),
            ])
            ->filters([
                static::tahunKerjaFilter('pengajuanProgramKerja.penawaranProgramKerja', KonteksProgramKerja::tahunPelaksanaanIds()),
                static::unitKerjaFilter('pengajuanProgramKerja'),
                static::waktuPengajuanFilter(),
            ])
            ->recordActions([
                MediaAction::make('lihatProposal')
                    ->label('Lihat Proposal')
                    ->color('gray')
                    ->path('proposal_path'),
                ProsesPencairanAction::make(),
                TandaiDicairkanAction::make(),
                ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Detail Realisasi Program Kerja')
                    ->modalWidth(Width::SevenExtraLarge)
                    // Modal detail bersifat baca-saja: autofocus bawaan justru melompat ke
                    // elemen fokusabel pertama yang letaknya jauh di bawah sehingga modal
                    // terbuka dalam keadaan sudah ter-scroll.
                    ->modalAutofocus(false)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->extraModalFooterActions([
                        ProsesPencairanAction::make()->cancelParentActions(),
                        TandaiDicairkanAction::make()->cancelParentActions(),
                    ]),
            ]);
    }
}

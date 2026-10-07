<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Tables;

use App\Enums\EnumJenisRealisasi;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Actions\AjukanRealisasiAction;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\KirimLaporanRealisasiAction;
use App\Filament\Actions\MediaAction;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RealisasiProgramKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada realisasi program kerja')
            ->emptyStateDescription('Buat realisasi dari pengajuan yang telah diterima.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->modifyQueryUsing(function (Builder $query) use ($table): void {
                $unitKerjaId = $table->getLivewire()->unitKerjaId ?? null;

                if ($unitKerjaId !== null) {
                    $query->whereHas('pengajuanProgramKerja', fn (Builder $q): Builder => $q->where('unit_kerja_id', $unitKerjaId));
                }
            })
            ->columns([
                TextColumn::make('pengajuanProgramKerja.penawaranProgramKerja.tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->badge()
                    ->color(fn (RealisasiProgramKerja $record): string => $record->pengajuanProgramKerja?->penawaranProgramKerja?->tahunKerja?->status?->getColor() ?? 'gray')
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('jenis_realisasi')
                    ->label('Tipe Pengajuan')
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')
                    ->label('Unit Kerja')
                    ->toggleable(),
                TextColumn::make('nominal_diajukan')
                    ->label('Diajukan')
                    ->money('IDR')
                    ->placeholder(fn (RealisasiProgramKerja $record): string => static::placeholderAnggaran($record, '-'))
                    ->sortable(),
                TextColumn::make('nominal_disetujui')
                    ->label('Disetujui')
                    ->money('IDR')
                    ->placeholder(fn (RealisasiProgramKerja $record): string => static::placeholderAnggaran($record, 'Belum disetujui'))
                    ->sortable(),
                TextColumn::make('persentase_persetujuan')
                    ->label('Persetujuan')
                    ->badge()
                    ->state(fn (RealisasiProgramKerja $record): ?int => $record->persentasePersetujuan())
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 100 => 'success',
                        $state >= 75 => 'info',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : "{$state}%")
                    ->placeholder(fn (RealisasiProgramKerja $record): string => static::placeholderAnggaran($record, '-')),
                TextColumn::make('anggaran_digunakan')
                    ->label('Realisasi Akhir')
                    ->money('IDR')
                    // Capaian tanpa anggaran tidak menyerap apa pun, jadi Rp 0 diganti
                    // keterangan agar tidak terbaca sebagai realisasi yang gagal terserap.
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->adalahTanpaAnggaran() ? null : (string) $record->anggaran_digunakan)
                    ->placeholder(fn (RealisasiProgramKerja $record): string => static::placeholderAnggaran($record, '-'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_anggaran')
                    ->label('Status Anggaran')
                    ->badge()
                    ->placeholder(fn (RealisasiProgramKerja $record): string => static::placeholderAnggaran($record, '-'))
                    ->toggleable(),
                TextColumn::make('nominal_selisih_anggaran')
                    ->label('Selisih Anggaran')
                    ->money('IDR')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_penyelesaian_anggaran')
                    ->label('Tindak Lanjut Anggaran')
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('persentase_ketercapaian')
                    ->label('Ketercapaian')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 100 => 'success',
                        $state >= 75 => 'info',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : "{$state}%")
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                    ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->placeholder('Semua Tahun Kerja')
                    ->options(fn (): array => TahunKerja::query()
                        ->whereIn('id', KonteksProgramKerja::tahunPelaksanaanIds())
                        ->orderByDesc('tahun')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => TahunKerja::berjalan()?->getKey())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $tahunKerjaId): Builder => $query->whereHas(
                            'pengajuanProgramKerja.penawaranProgramKerja',
                            fn (Builder $penawaran): Builder => $penawaran->where('tahun_kerja_id', $tahunKerjaId),
                        ),
                    )),
                SelectFilter::make('jenis_realisasi')
                    ->label('Tipe Pengajuan')
                    ->options(EnumJenisRealisasi::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusRealisasi::class),
                SelectFilter::make('status_anggaran')
                    ->label('Status Anggaran')
                    ->options(EnumStatusAnggaran::class),
            ])
            ->recordActions([
                AjukanRealisasiAction::make(),
                KirimLaporanRealisasiAction::make(),
                ActionGroup::make([
                    MediaAction::make('lihatProposal')
                        ->label('Lihat Proposal')
                        ->path('proposal_path'),
                    MediaAction::make('lihatLaporan')
                        ->label('Lihat Laporan')
                        ->path('laporan_path'),
                    AuthorizedViewAction::make()->label('Lihat'),
                    // Form realisasi bertumpu pada nominal dan dokumen proposal, keduanya
                    // tidak berlaku bagi capaian tanpa anggaran.
                    AuthorizedEditAction::make()
                        ->label('Ubah')
                        ->hidden(fn (RealisasiProgramKerja $record): bool => $record->adalahTanpaAnggaran()),
                    CaptchaDeleteAction::make(),
                ]),
            ]);
    }

    /**
     * Teks pengganti kolom bernuansa anggaran: capaian tanpa anggaran menyebutkannya
     * secara eksplisit, sisanya memakai teks bawaan kolom.
     */
    protected static function placeholderAnggaran(RealisasiProgramKerja $record, string $bawaan): string
    {
        return $record->adalahTanpaAnggaran() ? 'Tanpa anggaran' : $bawaan;
    }
}

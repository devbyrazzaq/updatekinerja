<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Tables;

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
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')
                    ->label('Unit Kerja')
                    ->toggleable(),
                TextColumn::make('nominal_diajukan')
                    ->label('Diajukan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('nominal_disetujui')
                    ->label('Disetujui')
                    ->money('IDR')
                    ->placeholder('Belum disetujui')
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
                    ->placeholder('-'),
                TextColumn::make('anggaran_digunakan')
                    ->label('Realisasi Akhir')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_anggaran')
                    ->label('Status Anggaran')
                    ->badge()
                    ->placeholder('-')
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
                    AuthorizedEditAction::make()->label('Ubah'),
                    CaptchaDeleteAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Pemasukans\Tables;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumSumberPemasukan;
use App\Filament\Actions\AjukanPemasukanAction;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use App\Filament\Actions\MediaAction;
use App\Filament\Actions\UnggahBuktiPemasukanAction;
use App\Models\Pemasukan;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PemasukansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_pelaksanaan', 'desc')
            ->emptyStateHeading('Belum ada pemasukan')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk mencatat pemasukan unit kerja.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->modifyQueryUsing(function (Builder $query) use ($table): void {
                $unitKerjaId = $table->getLivewire()->unitKerjaId ?? null;

                if ($unitKerjaId !== null) {
                    $query->where('unit_kerja_id', $unitKerjaId);
                }
            })
            ->columns([
                TextColumn::make('tanggal_pelaksanaan')
                    ->label('Periode')
                    ->state(fn (Pemasukan $record): string => $record->labelPeriode())
                    ->sortable(),
                TextColumn::make('jenis_waktu')
                    ->label('Jenis Waktu')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rincian_kegiatan')
                    ->label('Rincian Kegiatan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge(),
                TextColumn::make('referensi')
                    ->label('Program Kerja')
                    ->state(fn (Pemasukan $record): string => match ($record->sumber) {
                        EnumSumberPemasukan::Pengajuan => $record->pengajuanProgramKerja?->penawaranProgramKerja?->name ?? '-',
                        EnumSumberPemasukan::Realisasi => $record->realisasiProgramKerja?->name ?? '-',
                        default => '-',
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('nominal_pendapatan')
                    ->label('Pendapatan')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Pemasukan $record): string => $record->labelStatus())
                    ->color(fn (Pemasukan $record): string => $record->status->getColor()),
                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sumber')->label('Jenis Sumber')->options(EnumSumberPemasukan::class),
                SelectFilter::make('status')->label('Status')->options(EnumStatusPemasukan::class),
            ])
            ->recordActions([
                AjukanPemasukanAction::make(),
                UnggahBuktiPemasukanAction::make(),
                MediaAction::make('lihatBukti')
                    ->label('Lihat Bukti')
                    ->color('gray')
                    ->path('bukti_path')
                    ->visible(fn (Pemasukan $record): bool => filled($record->bukti_path)),
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Lihat'),
                    AuthorizedEditAction::make()->label('Ubah')
                        ->visible(fn (Pemasukan $record): bool => $record->dapatDiubah()),
                    CaptchaDeleteAction::make()
                        ->visible(fn (Pemasukan $record): bool => $record->dapatDiubah()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CaptchaDeleteBulkAction::make(),
                ]),
            ]);
    }
}

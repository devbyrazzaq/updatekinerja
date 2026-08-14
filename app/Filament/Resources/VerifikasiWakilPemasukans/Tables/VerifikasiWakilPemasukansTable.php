<?php

namespace App\Filament\Resources\VerifikasiWakilPemasukans\Tables;

use App\Enums\EnumSumberPemasukan;
use App\Filament\Actions\MediaAction;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Models\Pemasukan;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VerifikasiWakilPemasukansTable
{
    use HasVerificationTableFilters;

    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Tidak ada pemasukan yang menunggu verifikasi Wakil Rektor')
            ->emptyStateDescription('Pemasukan yang disetujui Rektor akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('rincian_kegiatan')->label('Rincian Kegiatan')->searchable()->wrap(),
                TextColumn::make('unitKerja.name')->label('Unit Kerja')->searchable(),
                TextColumn::make('sumber')->label('Sumber')->badge(),
                TextColumn::make('periode')
                    ->label('Periode')
                    ->state(fn (Pemasukan $record): string => $record->labelPeriode()),
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
                TextColumn::make('created_at')->label('Dicatat')->dateTime('d F Y H:i')->sortable(),
            ])
            ->filters([
                // Tabel pemasukan menyimpan unit_kerja_id langsung, sehingga argumen relasi
                // dibiarkan kosong. Filter tahun kerja sengaja tidak dipakai — lihat catatan
                // pada getEloquentQuery() resource.
                static::unitKerjaFilter(),
                static::waktuPengajuanFilter(),
                static::waktuPengajuanFilter('tanggal_pelaksanaan', 'Periode Pelaksanaan', 'periode_pelaksanaan'),
                SelectFilter::make('sumber')->label('Jenis Sumber')->options(EnumSumberPemasukan::class),
            ])
            ->recordActions([
                MediaAction::make('lihatBukti')
                    ->label('Lihat Bukti')
                    ->color('gray')
                    ->path('bukti_path')
                    ->visible(fn (Pemasukan $record): bool => filled($record->bukti_path)),
                ActionGroup::make([
                    ViewAction::make()->label('Detail'),
                ]),
            ]);
    }
}

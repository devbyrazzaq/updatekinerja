<?php

namespace App\Filament\Resources\VerifikasiWakilRektors\Tables;

use App\Filament\Actions\MediaAction;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerifikasiWakilRektorsTable
{
    use HasVerificationTableFilters;

    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Tidak ada realisasi yang menunggu verifikasi Wakil Rektor')
            ->emptyStateDescription('Realisasi yang disetujui Rektor akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->columns([
                TextColumn::make('name')->label('Kegiatan')->searchable()->wrap(),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')->label('Unit Kerja')->searchable(),
                TextColumn::make('anggaran_digunakan')->label('Digunakan')->money('IDR')->sortable(),
                TextColumn::make('urgensi')->label('Urgensi')->badge()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                    ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
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
                ActionGroup::make([
                    ViewAction::make()->label('Detail'),
                ]),
            ]);
    }
}

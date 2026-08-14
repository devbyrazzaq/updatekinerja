<?php

namespace App\Filament\Resources\VerifikasiPengajuans\Tables;

use App\Enums\EnumHasilVerifikasi;
use App\Enums\EnumStatusPengajuan;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Models\PengajuanProgramKerja;
use App\Services\KonteksProgramKerja;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerifikasiPengajuansTable
{
    use HasVerificationTableFilters;

    /**
     * @param  class-string<VerifikasiPengajuanResource>  $resource  Menu pemanggil. Menentukan slot
     *                                                               tahun kerja yang disaring sekaligus permission verifikasi yang diperiksa,
     *                                                               karena grup Verifikasi Pengajuan dan Verifikasi Pengajuan Perencanaan
     *                                                               memakai tabel ini bersama-sama.
     */
    public static function configure(Table $table, string $resource = VerifikasiPengajuanResource::class): Table
    {
        return $table
            ->emptyStateHeading('Tidak ada pengajuan yang menunggu verifikasi')
            ->emptyStateDescription('Pengajuan program kerja yang diajukan akan tampil di sini.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->columns([
                TextColumn::make('penawaranProgramKerja.tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->badge()
                    ->color(fn (PengajuanProgramKerja $record): string => $record->penawaranProgramKerja?->tahunKerja?->status?->getColor() ?? 'gray')
                    ->toggleable(),
                TextColumn::make('penawaranProgramKerja.name')
                    ->label('Program Kerja')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable(),
                TextColumn::make('alokasi_anggaran')
                    ->label('Pengajuan Anggaran')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengaju')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d F Y H:i')
                    ->sortable(),
            ])
            ->filters([
                static::tahunKerjaFilter('penawaranProgramKerja', KonteksProgramKerja::tahunSlotIds($resource::slotTahunKerja())),
                static::unitKerjaFilter(),
                static::waktuPengajuanFilter(),
            ])
            ->recordActions([
                Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(fn (PengajuanProgramKerja $record): bool => $resource::currentUserCanVerify() && $resource::isPendingAtStage($record))
                    ->schema([
                        Radio::make('hasil')
                            ->label('Hasil Verifikasi')
                            ->options([
                                EnumHasilVerifikasi::Setuju->value => EnumHasilVerifikasi::Setuju->getLabel(),
                                EnumHasilVerifikasi::Revisi->value => EnumHasilVerifikasi::Revisi->getLabel(),
                                EnumHasilVerifikasi::Tolak->value => EnumHasilVerifikasi::Tolak->getLabel(),
                            ])
                            ->required()
                            ->live(),
                        RichEditor::make('catatan')
                            ->label('Catatan')
                            ->required(fn (Get $get): bool => $get('hasil') !== EnumHasilVerifikasi::Setuju->value),
                    ])
                    ->action(function (array $data, PengajuanProgramKerja $record): void {
                        $hasil = EnumHasilVerifikasi::from($data['hasil']);

                        $status = match ($hasil) {
                            EnumHasilVerifikasi::Setuju => EnumStatusPengajuan::Diterima,
                            EnumHasilVerifikasi::Revisi => EnumStatusPengajuan::Revisi,
                            EnumHasilVerifikasi::Tolak => EnumStatusPengajuan::Ditolak,
                        };

                        $record->update([
                            'status' => $status,
                            'catatan_verifikasi' => $data['catatan'] ?? null,
                            'verifikator_id' => auth()->id(),
                            'diverifikasi_at' => now(),
                        ]);

                        $record->catatLog($status, auth()->id(), $data['catatan'] ?? null);

                        if ($hasil === EnumHasilVerifikasi::Revisi) {
                            app(NotifikasiVerifikasi::class)->pengajuanRevisi($record, $data['catatan'] ?? null);
                        }

                        Notification::make()->title('Verifikasi tersimpan')->success()->send();
                    }),
                ActionGroup::make([
                    ViewAction::make()->label('Detail'),
                ]),
            ]);
    }
}

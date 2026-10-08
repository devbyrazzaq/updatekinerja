<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Tables;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use App\Models\PengajuanProgramKerja;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PengajuanProgramKerjasTable
{
    /**
     * @param  EnumStatusTahunKerja  $slot  Slot tahun kerja milik menu pemanggil; menentukan
     *                                      pilihan penyaring Tahun Kerja yang masuk akal ditawarkan.
     */
    public static function configure(Table $table, EnumStatusTahunKerja $slot = EnumStatusTahunKerja::Berjalan): Table
    {
        return $table
            ->emptyStateHeading('Belum ada pengajuan program kerja')
            ->emptyStateDescription('Ajukan program kerja dari menu Daftar Program Kerja atau klik tambah.')
            ->emptyStateIcon('heroicon-o-inbox-arrow-down')
            ->modifyQueryUsing(function (Builder $query) use ($table): void {
                $unitKerjaId = $table->getLivewire()->unitKerjaId ?? null;

                if ($unitKerjaId !== null) {
                    $query->where('unit_kerja_id', $unitKerjaId);
                }
            })
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
                    ->toggleable(),
                TextColumn::make('alokasi_anggaran')
                    ->label('Anggaran')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_kerja')
                    ->label('Tahun Kerja')
                    ->options(fn (): array => TahunKerja::query()
                        ->whereIn('id', KonteksProgramKerja::tahunSlotIds($slot))
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => KonteksProgramKerja::tahunSlot($slot)?->getKey())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $tahunKerjaId): Builder => $query->whereHas(
                            'penawaranProgramKerja',
                            fn (Builder $penawaran): Builder => $penawaran->where('tahun_kerja_id', $tahunKerjaId),
                        ),
                    )),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusPengajuan::class),
            ])
            ->recordActions([
                Action::make('ajukan')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan Program Kerja')
                    ->modalDescription('Pengajuan akan dikirim untuk verifikasi tahap 1 dan tidak dapat diubah sampai ada keputusan.')
                    ->visible(fn (PengajuanProgramKerja $record, Page $livewire): bool => $livewire::getResource()::currentUserCanAbility('update')
                        && in_array($record->status, [EnumStatusPengajuan::Draft, EnumStatusPengajuan::Revisi], true))
                    ->action(function (PengajuanProgramKerja $record): void {
                        $diajukanKembali = $record->status === EnumStatusPengajuan::Revisi;

                        $record->update([
                            'status' => EnumStatusPengajuan::Diajukan,
                            'catatan_verifikasi' => null,
                        ]);

                        app(NotifikasiVerifikasi::class)->pengajuanDiajukan($record, $diajukanKembali);

                        Notification::make()->title('Pengajuan berhasil diajukan')->success()->send();
                    }),
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Lihat'),
                    AuthorizedEditAction::make()->label('Ubah'),
                    CaptchaDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CaptchaDeleteBulkAction::make(),
                ]),
            ]);
    }
}

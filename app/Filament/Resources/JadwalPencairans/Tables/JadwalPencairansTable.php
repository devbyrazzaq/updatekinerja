<?php

namespace App\Filament\Resources\JadwalPencairans\Tables;

use App\Enums\EnumStatusPencairan;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Models\JadwalPencairan;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JadwalPencairansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_pencairan')
            ->emptyStateHeading('Belum ada jadwal pencairan')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk membuat jadwal pencairan baru.')
            ->emptyStateIcon('heroicon-o-calendar-date-range')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Jadwal')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('tanggal_pencairan')
                    ->label('Tanggal Pencairan')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('jumlah_realisasi')
                    ->label('Realisasi')
                    ->state(fn (JadwalPencairan $record): string => $record->jumlahRealisasi().' realisasi')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('total_nominal')
                    ->label('Total Pencairan')
                    ->state(fn (JadwalPencairan $record): float => $record->totalNominal())
                    ->money('IDR')
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('dicairkan_at')
                    ->label('Dicairkan')
                    ->dateTime('d F Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusPencairan::class),
            ])
            ->recordActions([
                static::cairkanAction(),
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Detail'),
                    AuthorizedEditAction::make()->label('Ubah'),
                    CaptchaDeleteAction::make(),
                ]),
            ]);
    }

    /**
     * Menandai seluruh realisasi pada jadwal ini sudah menerima anggaran. Cara
     * pembayaran tiap realisasi memakai rencana yang dipilih saat penjadwalan
     * (transfer ke rekening masing-masing unit atau tunai), sehingga satu jadwal
     * boleh memuat campuran keduanya.
     */
    public static function cairkanAction(): Action
    {
        return Action::make('tandaiDicairkan')
            ->label('Tandai Dicairkan')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Tandai Anggaran Sudah Diterima')
            ->modalDescription(fn (JadwalPencairan $record): string => 'Total Rp '.number_format($record->totalNominal(), 0, ',', '.').' untuk '.$record->jumlahRealisasi().' realisasi dinyatakan sudah diserahkan sesuai cara pembayaran masing-masing ('.$record->ringkasanMetodePembayaran().'). Unit kerja akan diminta mengunggah laporan realisasi.')
            ->modalSubmitActionLabel('Tandai Dicairkan')
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalWidth(Width::Medium)
            ->visible(fn (JadwalPencairan $record): bool => ! $record->sudahDicairkan()
                && $record->jumlahRealisasi() > 0
                && JadwalPencairanResource::currentUserCanCairkan())
            ->action(function (JadwalPencairan $record): void {
                // Pencairan dibatalkan seluruhnya selama masih ada realisasi yang cara
                // pembayarannya belum lengkap, agar tidak ada anggaran yang tercatat cair
                // tanpa tujuan yang jelas.
                $belumLengkap = $record->realisasiBelumSiapDicairkan();

                if ($belumLengkap->isNotEmpty()) {
                    Notification::make()
                        ->title('Cara pembayaran belum lengkap')
                        ->body('Tentukan tipe pembayaran dan rekening tujuan lebih dulu untuk: '.$belumLengkap->pluck('name')->implode(', ').'.')
                        ->danger()
                        ->send();

                    return;
                }

                $jumlah = $record->cairkan(auth()->id());

                Notification::make()
                    ->title('Anggaran ditandai sudah dicairkan')
                    ->body($jumlah.' realisasi kini menunggu laporan pelaksanaan dari unit kerja.')
                    ->success()
                    ->send();
            });
    }
}

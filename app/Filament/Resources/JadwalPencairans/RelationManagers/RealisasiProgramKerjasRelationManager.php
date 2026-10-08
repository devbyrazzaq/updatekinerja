<?php

namespace App\Filament\Resources\JadwalPencairans\RelationManagers;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasPencairanPembayaran;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Models\JadwalPencairan;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * Daftar realisasi yang dijadwalkan pada satu jadwal pencairan. Selain menampilkan
 * rincian anggaran yang akan cair, pengguna dapat menjadwalkan realisasi lain ke
 * jadwal ini, mencairkan satu realisasi saja, atau mengeluarkannya dari jadwal
 * selama anggarannya belum diserahkan.
 */
class RealisasiProgramKerjasRelationManager extends RelationManager
{
    use HasPencairanPembayaran;

    protected static string $relationship = 'realisasiProgramKerjas';

    protected static ?string $title = 'Realisasi Dijadwalkan';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Dikirim halaman detail setelah jadwal dicairkan sekaligus, agar tabel ini
     * ikut menampilkan status realisasi terbaru tanpa memuat ulang halaman.
     */
    public const EVENT_JADWAL_DIPERBARUI = 'jadwal-pencairan-diperbarui';

    #[On(self::EVENT_JADWAL_DIPERBARUI)]
    public function muatUlangRealisasi(): void {}

    /**
     * Ringkasan total, status jadwal, dan tombol di header halaman detail turut
     * berubah ketika anggota jadwal diubah dari tabel ini.
     */
    protected function muatUlangHalaman(): void
    {
        $this->dispatch('refresh-page');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Belum ada realisasi pada jadwal ini')
            ->emptyStateDescription('Jadwalkan realisasi yang sudah lolos verifikasi Biro Keuangan melalui tombol di kanan atas.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['pengajuanProgramKerja.unitKerja', 'rekeningBank.bank']))
            ->columns([
                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable(),
                TextColumn::make('nominal_pencairan')
                    ->label('Nominal')
                    ->state(fn (RealisasiProgramKerja $record): float => $record->nominalPencairan())
                    ->money('IDR')
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('metode_pembayaran')
                    ->label('Tipe Pembayaran')
                    ->badge()
                    ->placeholder('Belum ditentukan'),
                TextColumn::make('rekeningBank.nomor_rekening')
                    ->label('Rekening Tujuan')
                    ->placeholder('-')
                    ->state(fn (RealisasiProgramKerja $record): ?string => $record->rekeningBank?->label())
                    ->wrap(),
                TextColumn::make('dicairkan_at')
                    ->label('Dicairkan')
                    ->dateTime('d F Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                $this->jadwalkanRealisasiAction(),
            ])
            ->recordActions([
                $this->cairkanRealisasiAction(),
                ActionGroup::make([
                    $this->keluarkanDariJadwalAction(),
                ]),
            ]);
    }

    /**
     * Menjadwalkan satu atau beberapa realisasi yang menunggu penjadwalan ke jadwal ini.
     */
    protected function jadwalkanRealisasiAction(): Action
    {
        return Action::make('jadwalkanRealisasi')
            ->label('Jadwalkan Realisasi')
            ->icon('heroicon-o-plus')
            ->modalHeading('Jadwalkan Realisasi ke Jadwal Ini')
            ->modalDescription('Pilih realisasi yang sudah lolos verifikasi Biro Keuangan untuk dicairkan pada jadwal ini.')
            ->modalSubmitActionLabel('Jadwalkan')
            ->modalWidth(Width::Large)
            ->visible(fn (): bool => ! $this->getOwnerRecord()->sudahDicairkan()
                && VerifikasiBiroKeuanganResource::currentUserCanVerify())
            ->schema([
                Select::make('realisasi_ids')
                    ->label('Realisasi')
                    ->options(fn (): array => static::opsiRealisasiMenungguJadwal())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->helperText('Hanya realisasi berstatus "Verifikasi Biro Keuangan" yang belum dijadwalkan.'),
            ])
            ->action(function (array $data): void {
                /** @var JadwalPencairan $jadwal */
                $jadwal = $this->getOwnerRecord();

                $realisasis = RealisasiProgramKerja::query()
                    ->whereKey($data['realisasi_ids'] ?? [])
                    ->where('status', EnumStatusRealisasi::VerifikasiKeuangan->value)
                    ->get();

                foreach ($realisasis as $realisasi) {
                    $realisasi->jadwalkanPencairan($jadwal, auth()->id());
                }

                $this->muatUlangHalaman();

                Notification::make()
                    ->title($realisasis->count().' realisasi dijadwalkan')
                    ->body('Realisasi menunggu anggaran diberikan pada '.$jadwal->tanggal_pencairan?->locale('id')->translatedFormat('d F Y').'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Mencairkan satu realisasi saja, mis. bila anggaran unit kerja tertentu
     * diserahkan lebih dulu atau dengan cara pembayaran yang berbeda.
     */
    protected function cairkanRealisasiAction(): Action
    {
        return Action::make('tandaiDicairkan')
            ->label('Tandai Dicairkan')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading('Tandai Anggaran Sudah Diterima')
            ->modalDescription(fn (RealisasiProgramKerja $record): string => 'Anggaran Rp '.number_format($record->nominalPencairan(), 0, ',', '.').' untuk "'.$record->name.'" dinyatakan sudah diserahkan.')
            ->modalSubmitActionLabel('Tandai Dicairkan')
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalWidth(Width::Medium)
            ->fillForm(fn (RealisasiProgramKerja $record): array => static::nilaiAwalPembayaran($record))
            ->schema(static::skemaPembayaran())
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->dicairkan_at === null
                && JadwalPencairanResource::currentUserCanCairkan())
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $record->tandaiAnggaranDicairkan(
                    auth()->id(),
                    static::metodePembayaranDari($data),
                    static::rekeningBankDari($data),
                );

                $this->muatUlangHalaman();

                Notification::make()
                    ->title('Anggaran ditandai sudah dicairkan')
                    ->body('Unit kerja kini dapat mengirim laporan realisasi.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Mengeluarkan realisasi dari jadwal agar dapat dijadwalkan ulang, mis. bila
     * pencairannya digeser ke gelombang berikutnya.
     */
    protected function keluarkanDariJadwalAction(): Action
    {
        return Action::make('keluarkanDariJadwal')
            ->label('Keluarkan dari Jadwal')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Keluarkan Realisasi dari Jadwal')
            ->modalDescription('Realisasi kembali menunggu penjadwalan di menu Verifikasi Biro Keuangan.')
            ->modalSubmitActionLabel('Keluarkan')
            ->visible(fn (RealisasiProgramKerja $record): bool => $record->dicairkan_at === null
                && VerifikasiBiroKeuanganResource::currentUserCanVerify())
            ->action(function (RealisasiProgramKerja $record): void {
                $record->keluarkanDariJadwal(auth()->id());

                $this->muatUlangHalaman();

                Notification::make()
                    ->title('Realisasi dikeluarkan dari jadwal')
                    ->success()
                    ->send();
            });
    }

    /**
     * Realisasi yang lolos verifikasi Biro Keuangan namun belum masuk jadwal mana pun,
     * dalam konteks tahun kerja aktif.
     *
     * @return array<int, string>
     */
    public static function opsiRealisasiMenungguJadwal(): array
    {
        return KonteksProgramKerja::applyPelaksanaanVia(
            RealisasiProgramKerja::query()
                ->with('pengajuanProgramKerja.unitKerja')
                ->whereNull('jadwal_pencairan_id')
                ->where('status', EnumStatusRealisasi::VerifikasiKeuangan->value),
            'pengajuanProgramKerja.penawaranProgramKerja',
        )
            ->get()
            ->mapWithKeys(fn (RealisasiProgramKerja $realisasi): array => [
                $realisasi->getKey() => $realisasi->name
                    .' — '.($realisasi->pengajuanProgramKerja?->unitKerja?->name ?? 'Tanpa unit kerja')
                    .' (Rp '.number_format($realisasi->nominalPencairan(), 0, ',', '.').')',
            ])
            ->all();
    }
}

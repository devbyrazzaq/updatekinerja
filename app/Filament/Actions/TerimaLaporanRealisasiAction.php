<?php

namespace App\Filament\Actions;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;

/**
 * Aksi "Terima" pada verifikasi laporan realisasi: menyelesaikan realisasi setelah
 * laporan dinyatakan benar.
 *
 * Bila anggaran tergunakan semua, tidak ada selisih yang perlu dituntaskan sehingga
 * modalnya cukup berupa konfirmasi. Bila anggaran bersisa atau kurang, verifikator
 * menetapkan cara penyelesaiannya — kekurangan dilunasi dengan cara apa, sisa masuk
 * ke mana — dan pilihan itu menentukan status penyelesaian yang tercatat.
 */
class TerimaLaporanRealisasiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'terimaLaporan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Terima')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-badge')
            ->modalIconColor('success')
            ->modalHeading('Terima Laporan Realisasi')
            ->modalDescription(fn (RealisasiProgramKerja $record): string => static::deskripsiKonfirmasi($record))
            ->modalSubmitActionLabel('Terima Laporan')
            ->modalWidth(fn (RealisasiProgramKerja $record): Width => static::selisih($record) === null ? Width::Medium : Width::Large)
            ->visible(fn (RealisasiProgramKerja $record): bool => VerifikasiLaporanResource::currentUserCanVerifyRecord($record)
                && VerifikasiLaporanResource::isPendingAtStage($record))
            ->fillForm(fn (RealisasiProgramKerja $record): array => [
                'cara_penyelesaian_anggaran' => $record->cara_penyelesaian_anggaran?->value,
            ])
            ->schema(fn (RealisasiProgramKerja $record): array => static::skemaPenyelesaian($record))
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                static::simpanPenerimaan($data, $record);

                Notification::make()
                    ->title('Laporan disetujui, realisasi selesai')
                    ->body($record->keteranganPenyelesaianAnggaran())
                    ->success()
                    ->send();
            })
            ->cancelParentActions();
    }

    /**
     * Isian penetapan selisih anggaran; kosong bila anggaran tergunakan semua sehingga
     * modalnya menjadi konfirmasi biasa.
     *
     * @return array<int, Placeholder|Select>
     */
    protected static function skemaPenyelesaian(RealisasiProgramKerja $record): array
    {
        $status = static::selisih($record);

        if ($status === null) {
            return [];
        }

        return [
            Placeholder::make('ringkasan_selisih')
                ->label($status->labelSelisih())
                ->content(static::rupiah((float) ($record->nominal_selisih_anggaran ?? 0)))
                ->helperText(static::keteranganSelisih($record)),
            Select::make('cara_penyelesaian_anggaran')
                ->label(EnumCaraPenyelesaianAnggaran::labelUntuk($status))
                ->options(EnumCaraPenyelesaianAnggaran::opsiUntuk($status))
                ->required()
                ->live()
                ->helperText(fn (Get $get): string => static::cara($get('cara_penyelesaian_anggaran'))?->getDescription()
                    ?? 'Tentukan bagaimana selisih anggaran ini dituntaskan sebelum realisasi ditutup.'),
        ];
    }

    /**
     * Menutup realisasi beserta penetapan cara penyelesaian selisih anggarannya.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function simpanPenerimaan(array $data, RealisasiProgramKerja $record): void
    {
        $cara = static::cara($data['cara_penyelesaian_anggaran'] ?? null);
        $penyelesaian = $cara?->penyelesaian();

        $record->update([
            'status' => EnumStatusRealisasi::Selesai,
            'laporan_disetujui_at' => now(),
            'verifikator_laporan_id' => auth()->id(),
            ...($cara !== null ? [
                'cara_penyelesaian_anggaran' => $cara,
                'status_penyelesaian_anggaran' => $penyelesaian,
                'penyelesaian_anggaran_at' => $penyelesaian->sudahSelesai() ? now() : null,
            ] : []),
        ]);

        $record->catatLog(
            EnumStatusRealisasi::Selesai,
            auth()->id(),
            $cara !== null
                ? EnumCaraPenyelesaianAnggaran::labelUntuk($cara->statusAnggaran()).': '.$cara->getLabel().'. '.$cara->getDescription()
                : $record->keteranganPenyelesaianAnggaran(),
        );
    }

    /**
     * Ringkasan yang ditampilkan di kepala modal: ketercapaian target dan status
     * anggaran laporan, sebagai dasar keputusan verifikator.
     */
    protected static function deskripsiKonfirmasi(RealisasiProgramKerja $record): string
    {
        $ketercapaian = 'Ketercapaian target '.($record->persentase_ketercapaian ?? 0).'%.';
        $status = $record->status_anggaran;

        if ($status === null) {
            return $ketercapaian.' Realisasi akan ditutup sebagai selesai.';
        }

        return $ketercapaian.' '.$status->getLabel().'. '.($status === EnumStatusAnggaran::Habis
            ? 'Tidak ada selisih yang perlu dituntaskan, realisasi langsung ditutup sebagai selesai.'
            : 'Tetapkan cara penyelesaian selisihnya sebelum realisasi ditutup.');
    }

    /**
     * Perbandingan anggaran diterima terhadap realisasi akhir sebagai dasar selisih.
     */
    protected static function keteranganSelisih(RealisasiProgramKerja $record): string
    {
        return 'Anggaran diterima '.static::rupiah($record->nominalDiterima())
            .', realisasi akhir '.static::rupiah((float) $record->anggaran_digunakan).'.';
    }

    /**
     * Status anggaran laporan bila menyisakan selisih, null bila tergunakan semua
     * atau laporan belum menyebutkan status anggarannya.
     */
    protected static function selisih(RealisasiProgramKerja $record): ?EnumStatusAnggaran
    {
        $status = $record->status_anggaran;

        return ($status?->memerlukanSelisih() ?? false) ? $status : null;
    }

    /**
     * State select enum dapat berupa instance enum maupun nilai mentahnya.
     */
    protected static function cara(mixed $state): ?EnumCaraPenyelesaianAnggaran
    {
        return match (true) {
            $state instanceof EnumCaraPenyelesaianAnggaran => $state,
            blank($state) => null,
            default => EnumCaraPenyelesaianAnggaran::tryFrom((string) $state),
        };
    }

    protected static function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}

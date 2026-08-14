<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\EnumMetodePembayaran;
use App\Filament\Resources\RekeningBanks\Schemas\RekeningBankForm;
use App\Models\RealisasiProgramKerja;
use App\Models\RekeningBank;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Skema pembayaran pencairan anggaran: tipe rekening (transfer ke rekening bank atau
 * tunai) dan rekening tujuannya. Dipakai bersama oleh penjadwalan di Verifikasi Biro
 * Keuangan maupun penandaan pencairan di Jadwal Pencairan, agar keduanya merekam data
 * pembayaran yang sama.
 *
 * Rekening tujuan diambil dari master Rekening Bank: hanya rekening milik unit kerja
 * pengaju dan rekening umum yang ditawarkan, dengan rekening utama unit tersebut
 * terpilih otomatis namun tetap dapat diganti.
 */
trait HasPencairanPembayaran
{
    /**
     * @return array<int, Select>
     */
    protected static function skemaPembayaran(): array
    {
        $isTransfer = static::metodeAdalahTransfer();

        return [
            Select::make('metode_pembayaran')
                ->label('Tipe Pembayaran')
                ->options(EnumMetodePembayaran::class)
                ->default(EnumMetodePembayaran::Transfer->value)
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->helperText('Cara anggaran diserahkan kepada unit kerja.'),
            Select::make('rekening_bank_id')
                ->label('Rekening Tujuan')
                ->options(fn (?RealisasiProgramKerja $record): array => RekeningBank::opsiUntukUnitKerja($record?->unitKerjaId()))
                ->default(fn (?RealisasiProgramKerja $record): ?int => RekeningBank::bawaanUntukUnitKerja($record?->unitKerjaId())?->getKey())
                ->searchable()
                ->visible($isTransfer)
                ->required($isTransfer)
                ->helperText('Terisi otomatis dengan rekening utama unit kerja pengaju bila sudah terdaftar; masih dapat diganti.')
                ->createOptionForm(fn (?RealisasiProgramKerja $record): array => RekeningBankForm::components($record?->unitKerjaId()))
                ->createOptionModalHeading('Tambah Rekening Bank')
                ->createOptionUsing(fn (array $data): int => RekeningBank::create($data)->getKey()),
        ];
    }

    /**
     * Nilai awal skema pembayaran untuk satu realisasi: rencana yang sudah dipilih
     * saat penjadwalan, atau rekening utama unit kerja bila belum ada.
     *
     * @return array<string, mixed>
     */
    protected static function nilaiAwalPembayaran(RealisasiProgramKerja $record): array
    {
        $metode = $record->metode_pembayaran ?? EnumMetodePembayaran::Transfer;

        return [
            'metode_pembayaran' => $metode->value,
            'rekening_bank_id' => $record->rekening_bank_id
                ?? RekeningBank::bawaanUntukUnitKerja($record->unitKerjaId())?->getKey(),
        ];
    }

    /**
     * State select bisa berupa enum maupun string, tergantung apakah sudah melewati
     * hidrasi form, sehingga keduanya dinormalkan sebelum dibandingkan.
     */
    protected static function metodeAdalahTransfer(): Closure
    {
        return fn (Get $get): bool => static::metodePembayaranDari(['metode_pembayaran' => $get('metode_pembayaran')])->isTransfer();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function metodePembayaranDari(array $data): EnumMetodePembayaran
    {
        $metode = $data['metode_pembayaran'] ?? null;

        if ($metode instanceof EnumMetodePembayaran) {
            return $metode;
        }

        return EnumMetodePembayaran::tryFrom((string) $metode) ?? EnumMetodePembayaran::Transfer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function rekeningBankDari(array $data): ?int
    {
        $rekeningBankId = $data['rekening_bank_id'] ?? null;

        return $rekeningBankId === null ? null : (int) $rekeningBankId;
    }
}

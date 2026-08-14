<?php

namespace App\Filament\Actions;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;

/**
 * Menutup selisih anggaran yang masih menggantung. Saat menerima laporan, verifikator
 * dapat memilih "Dilunasi Biro Keuangan (Pencairan Tambahan)" yang tidak langsung
 * menuntaskan apa pun: realisasi selesai, tetapi selisihnya berstatus Menunggu dan
 * belum menyesuaikan anggaran unit kerja.
 *
 * Aksi ini milik Biro Keuangan, dipakai setelah pencairan tambahan benar-benar
 * dilakukan (atau setelah diputuskan cara lain). Selama masih Menunggu, tahun kerja
 * bersangkutan tidak dapat dikunci, sehingga aksi ini menjadi langkah terakhir
 * penutupan tahun.
 */
class TuntaskanSelisihAnggaranAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'tuntaskanSelisihAnggaran';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tuntaskan Selisih')
            ->icon('heroicon-o-scale')
            ->color('warning')
            ->modalHeading('Tuntaskan Selisih Anggaran')
            ->modalDescription(fn (RealisasiProgramKerja $record): string => static::deskripsi($record))
            ->modalSubmitActionLabel('Tuntaskan')
            ->modalIcon('heroicon-o-scale')
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->visible(fn (RealisasiProgramKerja $record): bool => VerifikasiBiroKeuanganResource::currentUserCanVerify()
                && static::perluDituntaskan($record))
            ->fillForm(fn (RealisasiProgramKerja $record): array => [
                'cara_penyelesaian_anggaran' => $record->cara_penyelesaian_anggaran?->value,
            ])
            ->schema(fn (RealisasiProgramKerja $record): array => static::skema($record))
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                static::simpan($data, $record);

                Notification::make()
                    ->title('Selisih anggaran dituntaskan')
                    ->body($record->keteranganPenyelesaianAnggaran())
                    ->success()
                    ->send();
            });
    }

    /**
     * Selisih yang masih menggantung: laporan menyebut ada sisa/kekurangan dan
     * tindak lanjutnya belum diputuskan tuntas.
     */
    public static function perluDituntaskan(RealisasiProgramKerja $record): bool
    {
        return $record->status_penyelesaian_anggaran === EnumStatusPenyelesaianAnggaran::Menunggu
            && ($record->status_anggaran?->memerlukanSelisih() ?? false);
    }

    /**
     * @return array<int, Placeholder|Select>
     */
    protected static function skema(RealisasiProgramKerja $record): array
    {
        $status = $record->status_anggaran ?? EnumStatusAnggaran::Kurang;

        return [
            Placeholder::make('ringkasan_selisih')
                ->label($status->labelSelisih())
                ->content(static::rupiah((float) ($record->nominal_selisih_anggaran ?? 0)))
                ->helperText(static::keteranganRencana($record)),
            Select::make('cara_penyelesaian_anggaran')
                ->label(EnumCaraPenyelesaianAnggaran::labelUntuk($status))
                ->options(static::opsiTuntas($status))
                ->required()
                ->live()
                ->helperText(fn (Get $get): string => static::cara($get('cara_penyelesaian_anggaran'))?->getDescription()
                    ?? 'Pilih cara yang benar-benar sudah dijalankan, karena anggaran unit kerja langsung disesuaikan.'),
        ];
    }

    /**
     * Cara penyelesaian yang langsung menuntaskan selisih. Cara yang justru kembali
     * berstatus Menunggu — pencairan tambahan yang belum terjadi — dibuang agar aksi
     * ini tidak berputar pada keadaan yang sama.
     *
     * @return array<string, string>
     */
    protected static function opsiTuntas(EnumStatusAnggaran $statusAnggaran): array
    {
        $opsi = [];

        foreach (EnumCaraPenyelesaianAnggaran::cases() as $cara) {
            if ($cara->statusAnggaran() === $statusAnggaran && $cara->penyelesaian()->sudahSelesai()) {
                $opsi[$cara->value] = $cara->getLabel();
            }
        }

        return $opsi;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function simpan(array $data, RealisasiProgramKerja $record): void
    {
        $cara = static::cara($data['cara_penyelesaian_anggaran'] ?? null);

        if ($cara === null) {
            return;
        }

        $record->update([
            'cara_penyelesaian_anggaran' => $cara,
            'status_penyelesaian_anggaran' => $cara->penyelesaian(),
            'penyelesaian_anggaran_at' => now(),
        ]);

        $record->catatPenyelesaianSelisih($cara, auth()->id());
    }

    /**
     * Kepala modal: apa yang dulu direncanakan verifikator laporan dan mengapa
     * selisihnya belum tuntas.
     */
    protected static function deskripsi(RealisasiProgramKerja $record): string
    {
        return ($record->keteranganPenyelesaianAnggaran() ?? 'Selisih anggaran belum dituntaskan.')
            .' Tetapkan cara penyelesaian yang sudah benar-benar dijalankan agar anggaran unit kerja disesuaikan dan tahun kerjanya dapat dikunci.';
    }

    protected static function keteranganRencana(RealisasiProgramKerja $record): string
    {
        $rencana = $record->cara_penyelesaian_anggaran;

        $dasar = 'Anggaran diterima '.static::rupiah($record->nominalDiterima())
            .', realisasi akhir '.static::rupiah((float) $record->anggaran_digunakan).'.';

        return $rencana === null
            ? $dasar
            : $dasar.' Rencana semula: '.$rencana->getLabel().'.';
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

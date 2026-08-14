<?php

namespace App\Filament\Actions;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\RealisasiProgramKerja;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

/**
 * Aksi "Setujui" pada verifikasi realisasi. Verifikator memilih cara menetapkan
 * nominal yang disetujui melalui select:
 *
 * - `tentukan`  : mengisi nominal (default terisi sesuai yang diajukan, dapat diubah).
 * - `sesuai`    : menyetujui persis sebesar yang diajukan tanpa mengubah nominal.
 * - `lewati`    : menyetujui tanpa menetapkan nominal (hanya tahap Rektor).
 *
 * Tahap Rektor boleh `lewati` (nominal ditetapkan Wakil Rektor) atau `tentukan`
 * (nominal final, tidak dapat diubah Wakil Rektor). Bila Rektor sudah menetapkan
 * nominal, tahap Wakil Rektor hanya meneruskan tanpa dapat mengubahnya.
 */
class SetujuiRealisasiAction extends TahapVerifikasiRealisasiAction
{
    public static function getDefaultName(): ?string
    {
        return 'setujui';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Setujui')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (RealisasiProgramKerja $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Setujui Realisasi')
            ->modalDescription('Tentukan nominal yang disetujui sebelum meneruskan ke tahap verifikasi berikutnya.')
            ->modalSubmitActionLabel('Setujui')
            ->modalIcon('heroicon-o-check-badge')
            ->modalIconColor('success')
            ->modalWidth(Width::Medium)
            ->modalAlignment(Alignment::Center)
            ->modalFooterActionsAlignment(Alignment::Center)
            ->fillForm(fn (RealisasiProgramKerja $record): array => [
                'mode' => static::defaultMode($record),
                'nominal' => $record->nominal_disetujui ?? $record->nominalDiajukan(),
            ])
            ->schema(fn (RealisasiProgramKerja $record): array => static::skemaPersetujuan($record))
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                static::simpanPersetujuan($data, $record);

                Notification::make()->title('Realisasi berhasil disetujui')->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function simpanPersetujuan(array $data, RealisasiProgramKerja $record): void
    {
        $tahap = static::tahap($record);

        if ($tahap === null) {
            return;
        }

        $nominal = match (true) {
            static::nominalFinalDariRektor($record) => $record->nominal_disetujui,
            ($data['mode'] ?? null) === 'tentukan' => $data['nominal'],
            ($data['mode'] ?? null) === 'sesuai' => $record->nominalDiajukan(),
            default => $record->nominal_disetujui,
        };

        // Verifikator tahap ini yang benar-benar menentukan nominal (bukan meneruskan
        // nominal final Rektor maupun melewati penetapan) dicatat sebagai penentu.
        $menetapkanNominal = ! static::nominalFinalDariRektor($record)
            && in_array($data['mode'] ?? null, ['tentukan', 'sesuai'], true);

        $record->update([
            'status' => $tahap['nextStatus'],
            'nominal_disetujui' => $nominal,
            $tahap['actor'] => auth()->id(),
            $tahap['timestamp'] => now(),
            ...($menetapkanNominal ? ['penentu_nominal_id' => auth()->id()] : []),
        ]);

        $record->catatLog($tahap['nextStatus'], auth()->id());

        if ($menetapkanNominal) {
            $record->catatPenetapanNominal((float) $nominal, auth()->id());
        }
    }

    /**
     * Skema modal persetujuan. Bila nominal sudah ditetapkan final oleh Rektor,
     * tahap Wakil Rektor hanya menampilkan nominal tanpa dapat mengubahnya.
     *
     * @return array<int, Placeholder|Select|MoneyInput>
     */
    protected static function skemaPersetujuan(RealisasiProgramKerja $record): array
    {
        if (static::nominalFinalDariRektor($record)) {
            return [
                Placeholder::make('nominal_final')
                    ->label('Nominal Disetujui')
                    ->content('Rp '.number_format((float) $record->nominal_disetujui, 0, ',', '.').' — telah ditetapkan final oleh Rektor dan tidak dapat diubah.'),
            ];
        }

        return [
            Select::make('mode')
                ->label('Persetujuan Anggaran')
                ->options(static::opsiMode($record))
                ->default(static::defaultMode($record))
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->helperText(fn (Get $get): ?string => static::keteranganMode($record, $get('mode'))),
            MoneyInput::make('nominal')
                ->label('Nominal Disetujui')
                ->required()
                ->visible(fn (Get $get): bool => $get('mode') === 'tentukan')
                ->helperText('Diajukan Rp '.number_format($record->nominalDiajukan(), 0, ',', '.').'. Sesuaikan bila perlu.'),
        ];
    }

    /**
     * Keterangan konsekuensi mode pada tahap Rektor: `lewati` menyerahkan penetapan
     * nominal ke Wakil Rektor, `tentukan` mengunci nominal sebagai keputusan final.
     */
    protected static function keteranganMode(RealisasiProgramKerja $record, ?string $mode): ?string
    {
        if (! (static::tahap($record)['allowLewati'] ?? false)) {
            return null;
        }

        return match ($mode) {
            'lewati' => 'Nominal akan ditetapkan oleh Wakil Rektor.',
            'tentukan' => 'Nominal bersifat final. Wakil Rektor tidak dapat mengubahnya.',
            default => null,
        };
    }

    /**
     * Benar bila record berada di tahap Wakil Rektor sementara Rektor sudah menetapkan
     * nominal final, sehingga Wakil Rektor tidak dapat mengubahnya.
     */
    protected static function nominalFinalDariRektor(RealisasiProgramKerja $record): bool
    {
        return (static::tahap($record)['actor'] ?? null) === 'wakil_id'
            && $record->nominal_disetujui !== null;
    }

    /**
     * Mode terpilih saat modal dibuka: tahap Rektor default melewati penetapan nominal
     * (diteruskan ke Wakil Rektor), sedangkan Wakil Rektor default menetapkan nominal.
     */
    protected static function defaultMode(RealisasiProgramKerja $record): string
    {
        return (static::tahap($record)['allowLewati'] ?? false) ? 'lewati' : 'tentukan';
    }

    /**
     * Pilihan mode persetujuan sesuai tahap: Rektor boleh melewati penetapan nominal,
     * Wakil Rektor wajib menetapkan (mengisi atau menyetujui sesuai anggaran).
     *
     * @return array<string, string>
     */
    protected static function opsiMode(RealisasiProgramKerja $record): array
    {
        if (static::tahap($record)['allowLewati'] ?? false) {
            return [
                'tentukan' => 'Tentukan',
                'lewati' => 'Lewati',
            ];
        }

        return [
            'tentukan' => 'Tentukan',
            'sesuai' => 'Sesuai Anggaran',
        ];
    }
}

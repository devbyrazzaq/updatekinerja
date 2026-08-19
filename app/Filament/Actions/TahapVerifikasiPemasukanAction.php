<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Models\Pemasukan;
use Filament\Actions\Action;

/**
 * Induk aksi keputusan verifikasi pemasukan unit (setujui / minta revisi / tolak).
 *
 * Seperti pada verifikasi realisasi, tahap disimpulkan dari status record — pemetaan
 * yang persis sama dengan `pendingStatuses()` masing-masing resource — sehingga satu
 * aksi yang sama dapat dipasang di halaman mana pun tanpa mengetahui pemanggilnya.
 */
abstract class TahapVerifikasiPemasukanAction extends Action
{
    /**
     * Konfigurasi tahap tempat record sedang menunggu keputusan; null bila record
     * tidak sedang menunggu tahap persetujuan mana pun.
     *
     * @return array{actor: string, timestamp: string, nextStatus: EnumStatusPemasukan, resource: class-string}|null
     */
    protected static function tahap(Pemasukan $record): ?array
    {
        return match ($record->status) {
            EnumStatusPemasukan::Diajukan, EnumStatusPemasukan::VerifikasiWakil => [
                'actor' => 'wakil_id',
                'timestamp' => 'disetujui_wakil_at',
                'nextStatus' => EnumStatusPemasukan::VerifikasiKeuangan,
                'resource' => VerifikasiWakilPemasukanResource::class,
            ],
            EnumStatusPemasukan::VerifikasiKeuangan => [
                'actor' => 'keuangan_id',
                'timestamp' => 'disetujui_keuangan_at',
                'nextStatus' => EnumStatusPemasukan::MenungguBukti,
                'resource' => VerifikasiKeuanganPemasukanResource::class,
            ],
            default => null,
        };
    }

    /**
     * Pengguna berhak memutuskan tahap tempat record ini berada.
     */
    protected static function bolehMemutuskan(Pemasukan $record): bool
    {
        $tahap = static::tahap($record);

        return $tahap !== null && $tahap['resource']::currentUserCanVerify();
    }
}

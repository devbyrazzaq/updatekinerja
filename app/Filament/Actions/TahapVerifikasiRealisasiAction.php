<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;

/**
 * Induk aksi keputusan verifikasi realisasi (setujui / minta revisi / tolak).
 *
 * Halaman detail verifikasi tahu tahapnya dari resource pemiliknya, sedangkan
 * halaman Penyelesaian Tahun Lalu memuat realisasi dari berbagai tahap sekaligus.
 * Karena itu tahap disimpulkan dari status record — pemetaan yang persis sama
 * dengan `pendingStatuses()` masing-masing resource — sehingga satu aksi yang sama
 * dapat dipasang di mana pun tanpa mengetahui halaman pemanggilnya.
 */
abstract class TahapVerifikasiRealisasiAction extends Action
{
    /**
     * Konfigurasi tahap tempat record sedang menunggu keputusan; null bila record
     * tidak sedang menunggu tahap persetujuan mana pun.
     *
     * @return array{actor: string, timestamp: string, nextStatus: EnumStatusRealisasi, allowLewati: bool, resource: class-string}|null
     */
    protected static function tahap(RealisasiProgramKerja $record): ?array
    {
        return match ($record->status) {
            EnumStatusRealisasi::Diajukan, EnumStatusRealisasi::VerifikasiRektor => [
                'actor' => 'rektor_id',
                'timestamp' => 'disetujui_rektor_at',
                'nextStatus' => EnumStatusRealisasi::VerifikasiWakil,
                'allowLewati' => true,
                'resource' => VerifikasiRektorResource::class,
            ],
            EnumStatusRealisasi::VerifikasiWakil => [
                'actor' => 'wakil_id',
                'timestamp' => 'disetujui_wakil_at',
                'nextStatus' => EnumStatusRealisasi::VerifikasiKeuangan,
                'allowLewati' => false,
                'resource' => VerifikasiWakilRektorResource::class,
            ],
            default => null,
        };
    }

    /**
     * Pengguna berhak memutuskan tahap tempat record ini berada.
     */
    protected static function bolehMemutuskan(RealisasiProgramKerja $record): bool
    {
        $tahap = static::tahap($record);

        return $tahap !== null && $tahap['resource']::currentUserCanVerify();
    }
}

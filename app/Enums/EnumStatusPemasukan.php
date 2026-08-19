<?php

namespace App\Enums;

use App\Models\Pemasukan;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status pemasukan unit sepanjang alur verifikasi Wakil Rektor → Biro Keuangan,
 * ditutup dengan unggahan bukti tanda terima oleh unit kerja. Hanya status
 * {@see self::Valid} yang dihitung sebagai pemasukan sungguhan pada Buku Anggaran.
 */
enum EnumStatusPemasukan: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case VerifikasiWakil = 'verifikasi_wakil';
    case VerifikasiKeuangan = 'verifikasi_keuangan';
    case MenungguBukti = 'menunggu_bukti';
    case Valid = 'valid';
    case Revisi = 'revisi';
    case Ditolak = 'ditolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Diajukan => 'Diajukan',
            self::VerifikasiWakil => 'Verifikasi Wakil Rektor',
            self::VerifikasiKeuangan => 'Verifikasi Biro Keuangan',
            self::MenungguBukti => 'Menunggu Bukti Tanda Terima',
            self::Valid => 'Valid',
            self::Revisi => 'Revisi',
            self::Ditolak => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Diajukan, self::VerifikasiWakil, self::VerifikasiKeuangan => 'info',
            self::MenungguBukti, self::Revisi => 'warning',
            self::Valid => 'success',
            self::Ditolak => 'danger',
        };
    }

    /**
     * Tahapan stepper tempat status ini berada. Revisi dikembalikan ke tahap
     * pencatatan karena unit kerja perlu memperbaiki; Ditolak diatribusikan ke tahap
     * Verifikasi Wakil Rektor sebagai titik masuk verifikasi — keduanya status
     * bercabang yang posisi sebenarnya dibaca dari riwayat lewat
     * {@see Pemasukan::tahapanStepper()}.
     */
    public function tahapan(): EnumTahapanPemasukan
    {
        return match ($this) {
            self::Draft, self::Revisi => EnumTahapanPemasukan::Draf,
            self::Diajukan, self::VerifikasiWakil, self::Ditolak => EnumTahapanPemasukan::VerifikasiWakil,
            self::VerifikasiKeuangan => EnumTahapanPemasukan::VerifikasiKeuangan,
            self::MenungguBukti => EnumTahapanPemasukan::BuktiTerima,
            self::Valid => EnumTahapanPemasukan::Valid,
        };
    }

    /**
     * Status bercabang yang keluar dari alur maju sehingga tidak menyimpan posisi
     * alurnya sendiri.
     *
     * @return array<int, self>
     */
    public static function statusCabang(): array
    {
        return [self::Revisi, self::Ditolak];
    }

    /**
     * Status yang dianggap "berjalan": sudah diajukan, belum Valid maupun Ditolak.
     *
     * @return array<int, self>
     */
    public static function berjalan(): array
    {
        return [
            self::Diajukan,
            self::VerifikasiWakil,
            self::VerifikasiKeuangan,
            self::MenungguBukti,
            self::Revisi,
        ];
    }

    public function isBerjalan(): bool
    {
        return in_array($this, self::berjalan(), true);
    }
}

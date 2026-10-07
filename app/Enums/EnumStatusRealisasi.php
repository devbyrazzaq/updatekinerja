<?php

namespace App\Enums;

use App\Filament\Widgets\StatusRealisasiWidget;
use App\Providers\Filament\AppPanelProvider;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnumStatusRealisasi: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case VerifikasiRektor = 'verifikasi_rektor';
    case VerifikasiWakil = 'verifikasi_wakil';
    case VerifikasiKeuangan = 'verifikasi_keuangan';
    case Dijadwalkan = 'dijadwalkan';
    case MenungguLaporan = 'menunggu_laporan';
    case VerifikasiLaporan = 'verifikasi_laporan';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case Revisi = 'revisi';
    case Dibatalkan = 'dibatalkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Diajukan => 'Diajukan',
            self::VerifikasiRektor => 'Verifikasi Rektor',
            self::VerifikasiWakil => 'Verifikasi Wakil Rektor',
            self::VerifikasiKeuangan => 'Verifikasi Biro Keuangan',
            self::Dijadwalkan => 'Menunggu Anggaran Diberikan',
            self::MenungguLaporan => 'Menunggu Laporan Realisasi',
            self::VerifikasiLaporan => 'Verifikasi Laporan',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
            self::Revisi => 'Revisi',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Status yang dianggap "berjalan": sudah diajukan namun belum selesai maupun
     * ditolak, sehingga masih memakai kuota realisasi berjalan unit kerja.
     *
     * @return array<int, self>
     */
    public static function berjalan(): array
    {
        return [
            self::Diajukan,
            self::VerifikasiRektor,
            self::VerifikasiWakil,
            self::VerifikasiKeuangan,
            self::Dijadwalkan,
            self::MenungguLaporan,
            self::VerifikasiLaporan,
            self::Revisi,
        ];
    }

    public function isBerjalan(): bool
    {
        return in_array($this, self::berjalan(), true);
    }

    /**
     * Tahapan stepper tempat status ini berada. Revisi dikembalikan ke tahap
     * Pengajuan Realisasi karena unit kerja perlu memperbaiki; Ditolak diatribusikan
     * ke tahap Verifikasi Rektor sebagai titik masuk verifikasi.
     */
    public function tahapan(): EnumTahapanRealisasi
    {
        return match ($this) {
            self::Draft, self::Revisi, self::Dibatalkan => EnumTahapanRealisasi::Draf,
            self::Diajukan, self::VerifikasiRektor, self::Ditolak => EnumTahapanRealisasi::VerifikasiRektor,
            self::VerifikasiWakil => EnumTahapanRealisasi::VerifikasiWakil,
            self::VerifikasiKeuangan => EnumTahapanRealisasi::VerifikasiKeuangan,
            self::Dijadwalkan => EnumTahapanRealisasi::Pencairan,
            self::MenungguLaporan => EnumTahapanRealisasi::Pelaksanaan,
            self::VerifikasiLaporan => EnumTahapanRealisasi::VerifikasiLaporan,
            self::Selesai => EnumTahapanRealisasi::Selesai,
        };
    }

    /**
     * Warna badge status. Tiap status memakai warnanya sendiri — tidak ada yang kembar —
     * supaya tahap yang sedang dijalani sebuah realisasi terbaca sekali lihat, baik pada
     * tabel, stepper, maupun irisan grafik sebaran status.
     *
     * Warna di luar palet bawaan Filament (indigo, violet, cyan, teal, orange, rose,
     * slate) didaftarkan pada {@see AppPanelProvider}, dan
     * padanan rgb-nya untuk grafik ada pada
     * {@see StatusRealisasiWidget}.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Diajukan => 'info',
            self::VerifikasiRektor => 'indigo',
            self::VerifikasiWakil => 'violet',
            self::VerifikasiKeuangan => 'cyan',
            self::Dijadwalkan => 'orange',
            self::MenungguLaporan => 'warning',
            self::VerifikasiLaporan => 'teal',
            self::Selesai => 'success',
            self::Ditolak => 'danger',
            self::Revisi => 'rose',
            self::Dibatalkan => 'slate',
        };
    }
}

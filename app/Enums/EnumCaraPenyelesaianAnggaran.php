<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Cara selisih anggaran laporan realisasi dituntaskan, ditetapkan verifikator laporan
 * saat menerima laporan. Tiga cara pertama menutup kekurangan anggaran, tiga sisanya
 * menentukan tujuan sisa anggaran. Setiap cara menentukan status penyelesaian yang
 * tercatat, sehingga penyesuaian anggaran unit kerja mengikuti keputusan verifikator.
 */
enum EnumCaraPenyelesaianAnggaran: string implements HasColor, HasDescription, HasLabel
{
    case TalanganUnitKerja = 'talangan_unit_kerja';
    case PencairanTambahan = 'pencairan_tambahan';
    case PotongAnggaranBerikutnya = 'potong_anggaran_berikutnya';
    case KembaliBiroKeuangan = 'kembali_biro_keuangan';
    case SaldoUnitKerja = 'saldo_unit_kerja';
    case DialihkanRealisasiLain = 'dialihkan_realisasi_lain';

    public function getLabel(): string
    {
        return match ($this) {
            self::TalanganUnitKerja => 'Sudah Ditalangi Unit Kerja',
            self::PencairanTambahan => 'Dilunasi Biro Keuangan (Pencairan Tambahan)',
            self::PotongAnggaranBerikutnya => 'Dipotong dari Anggaran Berikutnya',
            self::KembaliBiroKeuangan => 'Dikembalikan ke Biro Keuangan',
            self::SaldoUnitKerja => 'Menjadi Saldo Anggaran Unit Kerja',
            self::DialihkanRealisasiLain => 'Dialihkan ke Realisasi Program Kerja Lain',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::TalanganUnitKerja => 'Kekurangan sudah dibayar unit kerja sendiri dan tidak diganti, sehingga mengurangi anggaran yang bisa digunakan.',
            self::PencairanTambahan => 'Kekurangan dilunasi Biro Keuangan lewat pencairan tambahan. Anggaran unit kerja belum disesuaikan sampai pencairan itu terjadi.',
            self::PotongAnggaranBerikutnya => 'Kekurangan ditutup dengan memotong anggaran unit kerja pada kegiatan berikutnya.',
            self::KembaliBiroKeuangan => 'Sisa anggaran disetorkan kembali ke Biro Keuangan dan menambah anggaran yang bisa digunakan unit kerja.',
            self::SaldoUnitKerja => 'Sisa anggaran ditahan unit kerja sebagai saldo untuk program kerja lain.',
            self::DialihkanRealisasiLain => 'Sisa anggaran dialihkan untuk membiayai realisasi program kerja lain milik unit kerja ini.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PencairanTambahan => 'warning',
            self::TalanganUnitKerja, self::PotongAnggaranBerikutnya => 'danger',
            self::KembaliBiroKeuangan, self::SaldoUnitKerja, self::DialihkanRealisasiLain => 'success',
        };
    }

    /**
     * Arah selisih yang ditangani cara ini: kekurangan dilunasi, sisa disalurkan.
     */
    public function statusAnggaran(): EnumStatusAnggaran
    {
        return match ($this) {
            self::TalanganUnitKerja, self::PencairanTambahan, self::PotongAnggaranBerikutnya => EnumStatusAnggaran::Kurang,
            self::KembaliBiroKeuangan, self::SaldoUnitKerja, self::DialihkanRealisasiLain => EnumStatusAnggaran::Sisa,
        };
    }

    /**
     * Status penyelesaian yang tercatat bila cara ini dipilih. Pencairan tambahan
     * masih menunggu Biro Keuangan, sehingga belum menyesuaikan anggaran unit kerja.
     */
    public function penyelesaian(): EnumStatusPenyelesaianAnggaran
    {
        return match ($this) {
            self::PencairanTambahan => EnumStatusPenyelesaianAnggaran::Menunggu,
            self::TalanganUnitKerja, self::PotongAnggaranBerikutnya => EnumStatusPenyelesaianAnggaran::Dilunasi,
            self::KembaliBiroKeuangan, self::SaldoUnitKerja, self::DialihkanRealisasiLain => EnumStatusPenyelesaianAnggaran::Dikembalikan,
        };
    }

    /**
     * Judul isian cara penyelesaian sesuai arah selisihnya.
     */
    public static function labelUntuk(EnumStatusAnggaran $statusAnggaran): string
    {
        return match ($statusAnggaran) {
            EnumStatusAnggaran::Kurang => 'Kekurangan Dilunasi Dengan Cara',
            EnumStatusAnggaran::Sisa => 'Sisa Anggaran Masuk Ke',
            EnumStatusAnggaran::Habis => 'Cara Penyelesaian Anggaran',
        };
    }

    /**
     * Pilihan cara yang masuk akal untuk sebuah status anggaran; kosong bila anggaran
     * tergunakan semua karena tidak ada selisih yang perlu dituntaskan.
     *
     * @return array<string, string>
     */
    public static function opsiUntuk(EnumStatusAnggaran $statusAnggaran): array
    {
        $opsi = [];

        foreach (self::cases() as $cara) {
            if ($cara->statusAnggaran() === $statusAnggaran) {
                $opsi[$cara->value] = $cara->getLabel();
            }
        }

        return $opsi;
    }
}

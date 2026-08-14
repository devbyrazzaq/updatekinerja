<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Tindak lanjut selisih anggaran pada laporan realisasi: sisa yang sudah
 * dikembalikan atau kekurangan yang sudah dilunasi ikut menyesuaikan anggaran yang
 * bisa digunakan unit kerja, sedangkan `Menunggu` belum menyesuaikan apa pun
 * karena masih menunggu respons Biro Keuangan.
 */
enum EnumStatusPenyelesaianAnggaran: string implements HasColor, HasDescription, HasLabel
{
    case Dikembalikan = 'dikembalikan';
    case Dilunasi = 'dilunasi';
    case Menunggu = 'menunggu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dikembalikan => 'Sisa Anggaran Sudah Dikembalikan',
            self::Dilunasi => 'Kekurangan Anggaran Sudah Dilunasi',
            self::Menunggu => 'Belum, Menunggu Respons Biro Keuangan',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Dikembalikan => 'Sisa anggaran menambah anggaran yang bisa digunakan unit kerja.',
            self::Dilunasi => 'Kekurangan anggaran mengurangi anggaran yang bisa digunakan unit kerja.',
            self::Menunggu => 'Selisih anggaran belum diperhitungkan sampai Biro Keuangan menindaklanjutinya.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dikembalikan, self::Dilunasi => 'success',
            self::Menunggu => 'warning',
        };
    }

    /**
     * Selisih anggaran sudah dituntaskan, sehingga ikut diperhitungkan pada anggaran
     * yang bisa digunakan unit kerja.
     */
    public function sudahSelesai(): bool
    {
        return $this !== self::Menunggu;
    }

    /**
     * Nilai status yang sudah dituntaskan, untuk menyaring kueri penyesuaian anggaran.
     *
     * @return array<int, string>
     */
    public static function nilaiSelesai(): array
    {
        return [self::Dikembalikan->value, self::Dilunasi->value];
    }

    /**
     * Pilihan tindak lanjut yang masuk akal untuk sebuah status anggaran: sisa hanya
     * dapat dikembalikan, kekurangan hanya dapat dilunasi, keduanya boleh ditunda.
     *
     * @return array<string, string>
     */
    public static function opsiUntuk(EnumStatusAnggaran $statusAnggaran): array
    {
        $tuntas = match ($statusAnggaran) {
            EnumStatusAnggaran::Sisa => self::Dikembalikan,
            EnumStatusAnggaran::Kurang => self::Dilunasi,
            EnumStatusAnggaran::Habis => null,
        };

        if ($tuntas === null) {
            return [];
        }

        return [
            $tuntas->value => $tuntas->getLabel(),
            self::Menunggu->value => self::Menunggu->getLabel(),
        ];
    }
}

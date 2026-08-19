<?php

namespace App\Enums;

use App\Exports\Export;
use App\Exports\ExportTheme;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tipe tampilan satu kolom ekspor. Menentukan perataan, format angka pada berkas
 * .xlsx, dan cara nilai dirender pada laporan PDF — sehingga kedua keluaran
 * memakai bahasa visual yang sama.
 *
 * Tipe ditebak otomatis dari nama kolom lewat {@see self::tebak()}; sebuah
 * {@see Export} boleh menegaskannya lewat `columnFormats()`.
 */
enum EnumFormatKolom: string
{
    case Teks = 'teks';
    case Angka = 'angka';
    case Uang = 'uang';
    case Persen = 'persen';
    case Tanggal = 'tanggal';
    case Waktu = 'waktu';
    case Boolean = 'boolean';
    case Tautan = 'tautan';

    /**
     * Tebak tipe dari nama kolom mesin, mis. `nominal_disetujui` → Uang.
     */
    public static function tebak(string $column): self
    {
        return match (true) {
            (bool) preg_match('/^(is|has|sudah|boleh)_/', $column) => self::Boolean,
            (bool) preg_match('/persentase|persen/', $column) => self::Persen,
            (bool) preg_match('/nominal|anggaran|alokasi|amount|saldo|debit|kredit|pemasukan|harga|biaya|pagu|terserap|komitmen|selisih/', $column) => self::Uang,
            (bool) preg_match('/_datetime$|_at$/', $column) => self::Waktu,
            (bool) preg_match('/^tanggal|_date$|^tgl/', $column) => self::Tanggal,
            // Sengaja tidak menyertakan `tahun`: tahun adalah penanda, bukan
            // kuantitas, jadi tidak boleh mendapat pemisah ribuan.
            (bool) preg_match('/^(jumlah|total|urutan|nilai_standar)$/', $column) => self::Angka,
            default => self::Teks,
        };
    }

    /**
     * Perataan horizontal kolom: angka rata kanan, penanda rata tengah.
     */
    public function perataan(): string
    {
        return match ($this) {
            self::Angka, self::Uang, self::Persen => 'right',
            self::Tanggal, self::Waktu, self::Boolean => 'center',
            self::Teks, self::Tautan => 'left',
        };
    }

    /**
     * Kode format angka Excel; null berarti sel dibiarkan apa adanya.
     */
    public function formatExcel(): ?string
    {
        return match ($this) {
            self::Uang => '#,##0',
            self::Angka => '#,##0',
            self::Persen => '0.0"%"',
            default => null,
        };
    }

    /**
     * Lebar kolom minimum (satuan karakter Excel) agar isi tidak terpotong.
     */
    public function lebarMinimum(): float
    {
        return match ($this) {
            self::Uang => 16,
            self::Waktu => 18,
            self::Tanggal, self::Persen => 14,
            self::Tautan => 18,
            self::Boolean => 12,
            default => 10,
        };
    }

    /**
     * Bobot pembagian lebar kolom pada tabel PDF, dinyatakan sebagai perkiraan
     * jumlah karakter yang perlu ditampung. Memakai kebutuhan isi — bukan rasio
     * bebas — supaya kolom angka dan tanggal tidak ikut menyusut ketika tabelnya
     * kebetulan berisi banyak kolom teks; kolom teks yang menyerap sisa ruang.
     */
    public function bobotLebar(): float
    {
        return match ($this) {
            self::Teks => 22,
            self::Tautan => 17,
            self::Waktu => 17,
            self::Uang => 14,
            self::Tanggal => 12,
            self::Boolean => 11,
            self::Angka => 9,
            self::Persen => 9,
        };
    }

    /**
     * Nilai yang sebaiknya utuh dalam satu baris — angka, tanggal, penanda.
     * Pada tabel yang sangat lebar aturan ini dilepas oleh view supaya isi sel tidak
     * melimpah menimpa kolom sebelahnya.
     */
    public function isNowrap(): bool
    {
        return $this !== self::Teks;
    }

    /**
     * Render nilai untuk laporan PDF. Sel kosong selalu menjadi tanda pisah agar
     * tabel tetap terbaca sebagai kisi.
     */
    public function tampilkan(mixed $value): string
    {
        if (blank($value) && ! is_numeric($value)) {
            return '—';
        }

        return match ($this) {
            self::Uang => 'Rp '.number_format((float) $value, 0, ',', '.'),
            self::Angka => number_format((float) $value, 0, ',', '.'),
            self::Persen => number_format((float) $value, 1, ',', '.').'%',
            self::Boolean => $this->tampilkanBoolean($value),
            self::Tanggal => $this->tampilkanTanggal($value, 'd M Y'),
            self::Waktu => $this->tampilkanTanggal($value, 'd M Y, H:i'),
            // Tautan hanya menyumbang teksnya; alamatnya dipasang oleh keluaran
            // masing-masing (rumus HYPERLINK pada .xlsx, anchor pada PDF).
            self::Teks, self::Tautan => Str::of((string) $value)->stripTags()->squish()->toString(),
        };
    }

    /**
     * Kolom bertipe penanda dirender sebagai lencana pada PDF.
     */
    public function isBadge(): bool
    {
        return $this === self::Boolean;
    }

    protected function tampilkanBoolean(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Aktif' : 'Nonaktif';
    }

    protected function tampilkanTanggal(mixed $value, string $format): string
    {
        try {
            $date = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return (string) $value;
        }

        return $date->locale(ExportTheme::LOCALE)->translatedFormat($format);
    }
}

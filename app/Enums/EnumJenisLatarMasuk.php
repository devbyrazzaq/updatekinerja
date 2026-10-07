<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis latar yang tampil di panel brand halaman masuk. Bawaannya gambar; video dan
 * YouTube diputar tanpa suara dan berulang, jadi tetap terbaca sebagai latar, bukan
 * tontonan.
 */
enum EnumJenisLatarMasuk: string implements HasLabel
{
    /** Gambar diam — bawaan sistem bila berkas lain belum diunggah. */
    case Gambar = 'gambar';

    /** Berkas video yang diunggah sendiri dan dilayani dari disk publik. */
    case Video = 'video';

    /** Video YouTube yang disematkan lewat tautannya. */
    case Youtube = 'youtube';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gambar => 'Gambar',
            self::Video => 'Berkas Video',
            self::Youtube => 'Video YouTube',
        };
    }
}

<?php

namespace App\Exports;

/**
 * Palet dan ukuran bersama untuk seluruh keluaran ekspor — berkas .xlsx maupun
 * laporan PDF membacanya dari sini supaya keduanya terlihat sebagai satu keluarga
 * dokumen. Warna ditulis sebagai heksa RGB tanpa tanda pagar agar langsung dipakai
 * openspout; {@see self::css()} menambahkan pagar untuk kebutuhan Blade.
 *
 * Nada warna mengikuti token instansi pada `resources/css/app.css`: navy sebagai
 * warna struktural, emas sebagai aksen tunggal, sisanya abu netral.
 */
final class ExportTheme
{
    /** Warna teks judul dan angka penting. */
    public const INK = '0B1F3A';

    /** Warna pita kepala tabel dan blok judul. */
    public const NAVY = '1E3A8A';

    /** Aksen tunggal: garis penegas di bawah judul. */
    public const GOLD = 'D4A017';

    /** Latar baris selang-seling. */
    public const ZEBRA = 'F5F7FB';

    /** Permukaan terang untuk kartu ringkasan. */
    public const SURFACE = 'F7F8FB';

    /** Garis rambut pemisah antar sel. */
    public const HAIRLINE = 'D9DFEA';

    /** Teks sekunder: label, keterangan, catatan kaki. */
    public const MUTED = '64748B';

    public const WHITE = 'FFFFFF';

    /** Nama font yang dipakai pada berkas .xlsx maupun PDF. */
    public const FONT = 'Plus Jakarta Sans';

    /**
     * Bahasa penulisan tanggal pada dokumen ekspor. Ditetapkan tegas, tidak ikut
     * `app.locale`, karena dokumen resmi yang keluar dari sistem ini selalu
     * berbahasa Indonesia sekalipun antarmukanya disetel lain.
     */
    public const LOCALE = 'id';

    /** Batas bawah lebar kolom .xlsx (satuan karakter Excel). */
    public const COLUMN_WIDTH_MIN = 10.0;

    /** Batas atas lebar kolom .xlsx supaya kolom teks panjang tidak melebar liar. */
    public const COLUMN_WIDTH_MAX = 52.0;

    /**
     * Berkas font yang disematkan ke laporan PDF, relatif terhadap `resources/fonts`.
     * Keduanya berupa font variabel, satu berkas menanggung seluruh bobot 200–800.
     *
     * @var array<string, string> subset => nama berkas
     */
    protected const FONT_FILES = [
        'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD' => 'plus-jakarta-sans-latin.woff2',
        'U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF' => 'plus-jakarta-sans-latin-ext.woff2',
    ];

    /**
     * Warna yang sama dalam bentuk siap pakai CSS, mis. `ExportTheme::css('NAVY')`.
     */
    public static function css(string $constant): string
    {
        /** @var string $value */
        $value = constant(self::class.'::'.$constant);

        return '#'.$value;
    }

    /**
     * Aturan `@font-face` Plus Jakarta Sans dengan berkas font tertanam sebagai data
     * URI. Disematkan alih-alih ditautkan ke Google Fonts karena Chromium yang
     * merender PDF berjalan di dalam container tanpa jaminan akses jaringan — font
     * yang gagal diunduh akan diam-diam jatuh ke font bawaan.
     */
    public static function fontFaceCss(): string
    {
        return once(function (): string {
            $rules = '';

            foreach (self::FONT_FILES as $unicodeRange => $file) {
                $path = resource_path('fonts/plus-jakarta-sans/'.$file);

                if (! is_file($path)) {
                    continue;
                }

                $data = base64_encode((string) file_get_contents($path));

                $rules .= <<<CSS
                    @font-face {
                        font-family: 'Plus Jakarta Sans';
                        font-style: normal;
                        font-weight: 200 800;
                        font-display: block;
                        src: url(data:font/woff2;charset=utf-8;base64,{$data}) format('woff2');
                        unicode-range: {$unicodeRange};
                    }

                    CSS;
            }

            return $rules;
        });
    }
}

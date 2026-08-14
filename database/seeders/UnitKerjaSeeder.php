<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

/**
 * Daftar unit kerja UMLA, disalin dari tabel `departements` basis data lama
 * (db_eval_kinerja.sql) beserta slug aslinya supaya tautan lama tetap sahih.
 */
class UnitKerjaSeeder extends Seeder
{
    /**
     * Slug => nama unit kerja, urut mengikuti data sumber.
     *
     * @var array<string, string>
     */
    public const UNIT_KERJAS = [
        'rektorat' => 'Rektorat',
        'wakil-rektor-1' => 'Wakil Rektor 1',
        'wakil-rektor-2' => 'Wakil Rektor 2',
        'wakil-rektor-3' => 'Wakil Rektor 3',
        'biro-administrasi-akademik-dan-kemahasiswaan-baak' => 'Biro Administrasi Akademik dan Kemahasiswaan ( BAAK )',
        'biro-administrasi-keuangan-bak' => 'Biro Administrasi Keuangan ( BAK )',
        'biro-administrasi-umum-bau' => 'Biro Administrasi Umum ( BAU )',
        'biro-perencanaan-pembangunan-dan-pemeliharaan-sarana-prasarana-bp3s' => 'Biro Perencanaan Pembangunan dan Pemeliharaan Sarana Prasarana ( BP3S )',
        'fakultas-ekonomi-dan-bisnis-feb' => 'Fakultas Ekonomi dan Bisnis ( FEB )',
        'fakultas-ilmu-kesehatan-fik' => 'Fakultas Ilmu Kesehatan ( FIK )',
        'fakultas-sains-teknologi-dan-pendidikan-fstp' => 'Fakultas Sains, Teknologi dan Pendidikan ( FSTP )',
        'halal-center' => 'Halal Center',
        'kantor-urusan-internasional-kui' => 'Kantor Urusan Internasional ( KUI )',
        'lembaga-pengembangan-al-islam-dan-kemuhammadiyahan-labaik' => 'Lembaga Pengembangan Al-Islam dan Kemuhammadiyahan ( LABAIK )',
        'lembaga-kemakmuran-masjid' => 'Lembaga Kemakmuran Masjid',
        'lembaga-kemahasiswaan' => 'Lembaga Kemahasiswaan',
        'lembaga-sertifikasi' => 'Lembaga Sertifikasi',
        'lembaga-informasi-dan-komunikasi-lesikom' => 'Lembaga Informasi dan Komunikasi ( LESIKOM )',
        'lembaga-penjaminan-mutu-lpm' => 'Lembaga Penjaminan Mutu ( LPM )',
        'lembaga-penelitian-dan-pengabdian-kepada-masyarakat-lppm' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat ( LPPM )',
        'perpustakaan' => 'Perpustakaan',
        'pusat-bahasa' => 'Pusat Bahasa',
        'pusat-bisnis' => 'Pusat Bisnis',
        'pusat-hak-kekayaan-intelektual-dan-publikasi-ilmiah' => 'Pusat Hak Kekayaan Intelektual dan Publikasi Ilmiah',
        'pusat-teknologi-informasi-pti' => 'Pusat Teknologi Informasi ( PTI )',
        'pusat-laboratorium' => 'Pusat Laboratorium',
        'pusat-layanan-bimbingan-konseling' => 'Pusat Layanan Bimbingan Konseling',
        'pusat-pengembangan-jurnal' => 'Pusat Pengembangan Jurnal',
        'pusat-pengembangan-pembelajaran-dan-rpl' => 'Pusat Pengembangan Pembelajaran dan RPL',
        'pusat-tracer-study' => 'Pusat Tracer Study',
        'sumber-daya-insan-sdi' => 'Sumber Daya Insan ( SDI )',
        'sekretariat-rektor' => 'Sekretariat Rektor',
        'satuan-pengawas-internal-spi' => 'Satuan Pengawas Internal ( SPI )',
        'biro-administrasi-dan-keuangan-bak' => 'Biro Administrasi dan Keuangan ( BAK )',
    ];

    public function run(): void
    {
        foreach (static::UNIT_KERJAS as $slug => $name) {
            UnitKerja::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}

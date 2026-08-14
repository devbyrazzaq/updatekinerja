<?php

namespace App\Services;

use App\Enums\EnumModeGenerate;
use App\Models\AcuanProgramKerja;
use App\Models\KelompokAcuan;
use App\Models\PenawaranProgramKerja;
use App\Models\TahunKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membentuk Penawaran Program Kerja dari seluruh Acuan aktif milik satu Kelompok
 * Acuan untuk satu Tahun Kerja. Target diambil dari AcuanTarget pada tahun yang
 * sama dengan tahun mulai Tahun Kerja tersebut.
 *
 * Penawaran hanya boleh lahir dari proses ini, sehingga seluruh penawaran pada satu
 * tahun kerja dianggap milik proses generate dan aman diperbarui atau dibentuk
 * ulang — kecuali yang sudah dipakai pengajuan.
 */
class GeneratePenawaranFromAcuan
{
    /**
     * @return array{created: int, updated: int, deleted: int, skipped: int}
     *
     * @throws RuntimeException bila mode tidak dapat dijalankan pada tahun kerja ini.
     */
    public function handle(
        KelompokAcuan $kelompokAcuan,
        TahunKerja $tahunKerja,
        EnumModeGenerate $mode = EnumModeGenerate::Baru,
        bool $onlyWithTarget = true,
    ): array {
        $alasan = static::alasanTidakBisa($tahunKerja, $mode);

        if ($alasan !== null) {
            throw new RuntimeException($alasan);
        }

        if ($mode === EnumModeGenerate::Lewati) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => static::jumlahPenawaran($tahunKerja)];
        }

        return DB::transaction(function () use ($kelompokAcuan, $tahunKerja, $mode, $onlyWithTarget): array {
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $deleted = $mode === EnumModeGenerate::Ulang
                ? static::penawaranQuery($tahunKerja)->delete()
                : 0;

            $tahun = $tahunKerja->tahunTarget();

            static::acuanQuery($kelompokAcuan)
                ->with('targets')
                ->each(function (AcuanProgramKerja $acuan) use ($tahunKerja, $tahun, $onlyWithTarget, &$created, &$updated, &$skipped): void {
                    $target = $acuan->targets->firstWhere('tahun', $tahun);

                    if ($onlyWithTarget && $target === null) {
                        $skipped++;

                        return;
                    }

                    $penawaran = PenawaranProgramKerja::updateOrCreate(
                        [
                            'acuan_program_kerja_id' => $acuan->id,
                            'tahun_kerja_id' => $tahunKerja->id,
                            'unit_kerja_id' => $acuan->unit_kerja_id,
                        ],
                        [
                            'name' => $acuan->name,
                            'bidang_id' => $acuan->bidang_id,
                            'kategori_id' => $acuan->kategori_id,
                            'program_id' => $acuan->program_id,
                            'rekening_id' => $acuan->rekening_id,
                            'aktifitas' => $acuan->aktifitas,
                            'indikator' => $acuan->indikator,
                            'nilai_standar' => $acuan->nilai_standar,
                            'satuan_nilai_standar' => $acuan->satuan_nilai_standar,
                            'target' => $target?->label(),
                            'is_active' => true,
                        ],
                    );

                    $penawaran->wasRecentlyCreated ? $created++ : $updated++;
                });

            return compact('created', 'updated', 'deleted', 'skipped');
        });
    }

    /**
     * Alasan mode tidak dapat dijalankan, atau null bila aman. Dipakai halaman
     * pengaturan untuk menghadang aksi lebih dulu, dan oleh handle() sebagai penjaga
     * terakhir.
     */
    public static function alasanTidakBisa(TahunKerja $tahunKerja, EnumModeGenerate $mode): ?string
    {
        $jumlahPenawaran = static::jumlahPenawaran($tahunKerja);

        if ($mode === EnumModeGenerate::Baru && $jumlahPenawaran > 0) {
            return "Tahun kerja \"{$tahunKerja->name}\" sudah memiliki {$jumlahPenawaran} penawaran. Pilih Perbarui Data Lama atau Generate Ulang.";
        }

        if ($mode !== EnumModeGenerate::Ulang) {
            return null;
        }

        $jumlahBerpengajuan = static::jumlahPenawaranBerpengajuan($tahunKerja);

        if ($jumlahBerpengajuan > 0) {
            return "Generate ulang dibatalkan: {$jumlahBerpengajuan} penawaran sudah memiliki pengajuan. Gunakan Perbarui Data Lama agar pengajuan yang sedang berjalan tidak ikut terhapus.";
        }

        return null;
    }

    /**
     * Acuan yang menjadi sumber penawaran: acuan aktif milik kelompok terpilih.
     *
     * @return Builder<AcuanProgramKerja>
     */
    public static function acuanQuery(KelompokAcuan $kelompokAcuan): Builder
    {
        return AcuanProgramKerja::query()
            ->where('kelompok_acuan_id', $kelompokAcuan->getKey())
            ->where('is_active', true);
    }

    /**
     * Penawaran yang sudah terbentuk pada satu tahun kerja.
     *
     * @return Builder<PenawaranProgramKerja>
     */
    public static function penawaranQuery(TahunKerja $tahunKerja): Builder
    {
        return PenawaranProgramKerja::query()->where('tahun_kerja_id', $tahunKerja->getKey());
    }

    public static function jumlahAcuan(KelompokAcuan $kelompokAcuan): int
    {
        return static::acuanQuery($kelompokAcuan)->count();
    }

    public static function jumlahPenawaran(TahunKerja $tahunKerja): int
    {
        return static::penawaranQuery($tahunKerja)->count();
    }

    public static function jumlahPenawaranBerpengajuan(TahunKerja $tahunKerja): int
    {
        return static::penawaranQuery($tahunKerja)->whereHas('pengajuanProgramKerjas')->count();
    }
}

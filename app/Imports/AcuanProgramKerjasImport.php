<?php

namespace App\Imports;

use App\Exports\AcuanProgramKerjasExport;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\Program;
use App\Models\Rekening;
use App\Models\UnitKerja;
use Closure;

class AcuanProgramKerjasImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new AcuanProgramKerjasExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['name', 'unit_kerja', 'bidang', 'kategori', 'program'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit_kerja' => ['required', 'string'],
            'bidang' => ['required', 'string'],
            'kategori' => ['required', 'string'],
            'program' => ['required', 'string'],
            'kode_akun' => ['nullable', 'string'],
            'aktifitas' => ['nullable', 'string'],
            'indikator' => ['nullable', 'string'],
            'nilai_standar' => ['nullable', 'string'],
            'satuan_nilai_standar' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function validateRow(array $row, int $rowNumber, Closure $fail): void
    {
        if (UnitKerja::where('name', $row['unit_kerja'] ?? null)->doesntExist()) {
            $fail("Unit Kerja \"{$row['unit_kerja']}\" tidak ditemukan.");
        }
        if (Bidang::where('name', $row['bidang'] ?? null)->doesntExist()) {
            $fail("Bidang \"{$row['bidang']}\" tidak ditemukan.");
        }
        if (Kategori::where('name', $row['kategori'] ?? null)->doesntExist()) {
            $fail("Kategori \"{$row['kategori']}\" tidak ditemukan.");
        }
        if (Program::where('name', $row['program'] ?? null)->doesntExist()) {
            $fail("Program \"{$row['program']}\" tidak ditemukan.");
        }
    }

    public function templateColumns(): array
    {
        return ['name', 'unit_kerja', 'bidang', 'kategori', 'program', 'kode_akun', 'aktifitas', 'indikator', 'nilai_standar', 'satuan_nilai_standar', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Peningkatan Mutu Pembelajaran', 'Fakultas Teknik', 'Akademik', 'Pendidikan', 'Tridharma Perguruan Tinggi', '5.1.02.01', 'Workshop dosen', 'Nilai mutu', '90', 'persen', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-acuan-program-kerja';
    }

    public function storeRow(array $row): void
    {
        $unitKerja = UnitKerja::where('name', $row['unit_kerja'])->first();
        $bidang = Bidang::where('name', $row['bidang'])->first();
        $kategori = Kategori::where('name', $row['kategori'])->first();
        $program = Program::where('name', $row['program'])->first();

        if ($unitKerja === null || $bidang === null || $kategori === null || $program === null) {
            return;
        }

        $rekeningId = ! empty($row['kode_akun'])
            ? Rekening::where('code', $row['kode_akun'])->value('id')
            : null;

        // Kelompok acuan diambil dari konteks (mis. saat impor dari halaman Kelompok
        // Acuan), jatuh kembali ke kelompok yang sedang aktif bila tak diberikan.
        $kelompokAcuanId = $this->context('kelompok_acuan_id') ?? KelompokAcuan::active()?->id;

        AcuanProgramKerja::updateOrCreate(
            ['name' => $row['name'], 'unit_kerja_id' => $unitKerja->id],
            [
                'kelompok_acuan_id' => $kelompokAcuanId,
                'bidang_id' => $bidang->id,
                'kategori_id' => $kategori->id,
                'program_id' => $program->id,
                'rekening_id' => $rekeningId,
                'aktifitas' => $row['aktifitas'] ?? null,
                'indikator' => $row['indikator'] ?? null,
                'nilai_standar' => $row['nilai_standar'] ?? null,
                'satuan_nilai_standar' => $row['satuan_nilai_standar'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}

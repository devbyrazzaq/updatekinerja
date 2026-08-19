<?php

namespace Tests\Feature;

use App\Enums\EnumFormatKolom;
use App\Exports\Export;
use App\Exports\KategorisExport;
use App\Exports\ProgramsExport;
use App\Exports\TemplateExport;
use App\Imports\KategorisImport;
use App\Imports\ProgramsImport;
use App\Models\Kategori;
use App\Models\Program;
use App\Services\Excel\SpreadsheetExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Menguji tata letak berkas .xlsx hasil ekspor: blok kepala dokumen, kepala tabel
 * dua tingkat, dan yang terpenting — berkas itu tetap bisa diimpor kembali.
 */
class EksporSpreadsheetDesainTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'uji_ekspor_').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * Baris berkas apa adanya, termasuk baris kosong penata jarak, supaya nomor
     * baris pada pengujian sama dengan yang dilihat pengguna di Excel.
     *
     * @return list<list<mixed>>
     */
    private function bacaBaris(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    public function test_berkas_diawali_blok_kepala_lalu_kepala_tabel_dua_tingkat(): void
    {
        Program::create(['name' => 'Tri Dharma', 'is_active' => true]);

        app(SpreadsheetExporter::class)->write(new ProgramsExport, $this->path);
        $rows = $this->bacaBaris($this->path);

        // Baris 1-3 blok kepala, baris 4 garis aksen, baris 5 penata jarak.
        $this->assertSame('Data Program Induk', $rows[0][0]);
        $this->assertStringContainsString('Daftar program induk', (string) $rows[1][0]);
        $this->assertStringContainsString('Dicetak', (string) $rows[2][0]);

        // Tingkat pertama label manusiawi, tingkat kedua key mesin, lalu data.
        $this->assertSame(['Nama Program Induk', 'Deskripsi', 'Status'], $rows[5]);
        $this->assertSame(['name', 'description', 'is_active'], $rows[6]);
        $this->assertSame('Tri Dharma', $rows[7][0]);
    }

    public function test_berkas_hasil_ekspor_bisa_diimpor_kembali(): void
    {
        Kategori::create(['code' => 'KAT-01', 'name' => 'Pendidikan', 'description' => 'Kegiatan pendidikan']);
        Kategori::create(['code' => 'KAT-02', 'name' => 'Penelitian']);

        app(SpreadsheetExporter::class)->write(new KategorisExport, $this->path);

        Kategori::query()->delete();

        $result = (new KategorisImport)->import($this->path);

        $this->assertFalse($result->failed(), implode(' | ', $result->errors));
        $this->assertSame(2, $result->imported);
        $this->assertDatabaseHas(Kategori::class, ['code' => 'KAT-01', 'name' => 'Pendidikan']);
        $this->assertDatabaseHas(Kategori::class, ['code' => 'KAT-02', 'name' => 'Penelitian']);
    }

    public function test_berkas_template_impor_juga_bisa_diimpor_kembali(): void
    {
        $import = new ProgramsImport;

        app(SpreadsheetExporter::class)->write(
            new TemplateExport('template', $import->templateColumns(), $import->sampleRows(), $import->columnLabels()),
            $this->path,
        );

        $result = $import->import($this->path);

        $this->assertFalse($result->failed(), implode(' | ', $result->errors));
        $this->assertSame(count($import->sampleRows()), $result->imported);
    }

    public function test_baris_dipisah_pita_pembatas_saat_ekspor_mengelompokkan(): void
    {
        $export = new class extends Export
        {
            public function filename(): string
            {
                return 'uji-pengelompokan';
            }

            public function title(): string
            {
                return 'Uji Pengelompokan';
            }

            public function headings(): array
            {
                return ['nama', 'dicairkan_at'];
            }

            public function rows(): iterable
            {
                return [
                    ['Kegiatan A', '2026-03-04'],
                    ['Kegiatan B', '2026-03-20'],
                    ['Kegiatan C', '2026-02-11'],
                    ['Kegiatan D', null],
                ];
            }

            public function groupLabel(array $row): ?string
            {
                $dicairkan = $this->columnValue($row, 'dicairkan_at');

                return blank($dicairkan)
                    ? 'Belum Dicairkan'
                    : 'Dicairkan '.mb_substr((string) $dicairkan, 0, 7);
            }
        };

        app(SpreadsheetExporter::class)->write($export, $this->path);
        $rows = $this->bacaBaris($this->path);

        // Tanpa anak judul: baris 5 label, baris 6 key mesin, lalu pita kelompok.
        $this->assertSame(['nama', 'dicairkan_at'], $rows[5]);

        $this->assertSame('Dicairkan 2026-03', $rows[6][0]);
        $this->assertSame('Kegiatan A', $rows[7][0]);
        $this->assertSame('Kegiatan B', $rows[8][0]);
        $this->assertSame('Dicairkan 2026-02', $rows[9][0]);
        $this->assertSame('Kegiatan C', $rows[10][0]);
        $this->assertSame('Belum Dicairkan', $rows[11][0]);
        $this->assertSame('Kegiatan D', $rows[12][0]);

        // Pembatas tidak menambah kolom: barisnya tetap selebar kepala tabel.
        $this->assertCount(2, $rows[7]);
    }

    public function test_ekspor_tanpa_data_tetap_menghasilkan_berkas_dengan_keterangan(): void
    {
        app(SpreadsheetExporter::class)->write(new ProgramsExport, $this->path);
        $rows = $this->bacaBaris($this->path);

        $this->assertSame('Belum ada data untuk diekspor.', $rows[7][0]);
    }

    public function test_tipe_kolom_ditebak_dari_namanya(): void
    {
        $this->assertSame(EnumFormatKolom::Uang, EnumFormatKolom::tebak('nominal_disetujui'));
        $this->assertSame(EnumFormatKolom::Uang, EnumFormatKolom::tebak('alokasi_anggaran'));
        $this->assertSame(EnumFormatKolom::Persen, EnumFormatKolom::tebak('persentase_ketercapaian'));
        $this->assertSame(EnumFormatKolom::Boolean, EnumFormatKolom::tebak('is_active'));
        $this->assertSame(EnumFormatKolom::Tanggal, EnumFormatKolom::tebak('tanggal_pelaksanaan'));
        $this->assertSame(EnumFormatKolom::Waktu, EnumFormatKolom::tebak('start_datetime'));
        $this->assertSame(EnumFormatKolom::Teks, EnumFormatKolom::tebak('keterangan'));

        // Tahun adalah penanda, bukan kuantitas — tidak boleh berpemisah ribuan.
        $this->assertSame(EnumFormatKolom::Teks, EnumFormatKolom::tebak('tahun'));
    }

    public function test_nilai_diformat_dalam_bahasa_indonesia(): void
    {
        $this->assertSame('Rp 1.250.000', EnumFormatKolom::Uang->tampilkan(1250000));
        $this->assertSame('87,5%', EnumFormatKolom::Persen->tampilkan(87.5));
        $this->assertSame('11 Mar 2026', EnumFormatKolom::Tanggal->tampilkan('2026-03-11'));
        $this->assertSame('11 Mar 2026, 09:30', EnumFormatKolom::Waktu->tampilkan('2026-03-11 09:30:00'));
        $this->assertSame('Aktif', EnumFormatKolom::Boolean->tampilkan(1));
        $this->assertSame('Nonaktif', EnumFormatKolom::Boolean->tampilkan(0));
        $this->assertSame('—', EnumFormatKolom::Teks->tampilkan(null));
    }
}

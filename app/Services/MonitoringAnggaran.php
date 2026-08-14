<?php

namespace App\Services;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka monitoring program kerja beberapa unit kerja pada satu tahun kerja: pagu,
 * penyerapan anggaran, distribusinya, dan capaian program kerjanya.
 *
 * Penyerapan memakai definisi yang sama dengan {@see BukuAnggaran} — berbasis kas,
 * jadi anggaran dianggap terserap saat benar-benar dicairkan (`dicairkan_at`), lalu
 * dikoreksi oleh penyelesaian selisih anggaran yang sudah dituntaskan. Bedanya, di
 * sini angkanya dijumlahkan langsung oleh basis data alih-alih dirakit baris demi
 * baris, karena monitoring hanya membutuhkan totalnya dan kerap membaca puluhan unit
 * kerja sekaligus.
 *
 * Pengelompokan bulan dilakukan di PHP, bukan lewat fungsi tanggal basis data, agar
 * hasilnya sama pada MySQL maupun SQLite.
 */
class MonitoringAnggaran
{
    /**
     * @var Collection<int, RingkasanMonitoring>|null
     */
    private ?Collection $perUnitKerja = null;

    /**
     * @var array<int, string>|null Nama unit kerja yang dibaca, id => nama.
     */
    private ?array $namaUnitKerja = null;

    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang dibaca.
     */
    public function __construct(
        public readonly array $unitKerjaIds,
        public readonly ?TahunKerja $tahunKerja = null,
    ) {}

    /**
     * @param  array<int, int>  $unitKerjaIds
     */
    public static function untukUnits(array $unitKerjaIds, ?TahunKerja $tahunKerja = null): self
    {
        return new self(array_values(array_map(intval(...), $unitKerjaIds)), $tahunKerja);
    }

    public static function untukUnit(int $unitKerjaId, ?TahunKerja $tahunKerja = null): self
    {
        return new self([$unitKerjaId], $tahunKerja);
    }

    /**
     * Cakupan monitoring kosong: tanpa tahun kerja maupun unit kerja, seluruh angka
     * bernilai nol tanpa menyentuh basis data.
     */
    public function kosong(): bool
    {
        return $this->tahunKerja === null || $this->unitKerjaIds === [];
    }

    /**
     * Angka gabungan seluruh unit kerja yang dibaca.
     */
    public function ringkasan(string $label = 'Seluruh Unit Kerja'): RingkasanMonitoring
    {
        if ($this->kosong()) {
            return RingkasanMonitoring::kosong(label: $label);
        }

        if (count($this->unitKerjaIds) === 1) {
            $tunggal = $this->perUnitKerja()->first();

            return $tunggal ?? RingkasanMonitoring::kosong($this->unitKerjaIds[0], $label);
        }

        return RingkasanMonitoring::gabung($this->perUnitKerja(), label: $label);
    }

    /**
     * Angka tiap unit kerja, terurut mengikuti nama unitnya. Unit yang belum punya
     * data apa pun tetap muncul dengan angka nol agar tidak hilang dari rekap.
     *
     * @return Collection<int, RingkasanMonitoring>
     */
    public function perUnitKerja(): Collection
    {
        if ($this->perUnitKerja !== null) {
            return $this->perUnitKerja;
        }

        if ($this->kosong()) {
            return $this->perUnitKerja = collect();
        }

        $pagu = $this->paguPerUnitKerja();
        $pencairan = $this->nominalPerUnitKerja($this->realisasiCairQuery());
        $komitmen = $this->komitmenPerUnitKerja();
        $selisih = $this->selisihPerUnitKerja();
        $program = $this->jumlahProgramPerUnitKerja();
        $programDiajukan = $this->jumlahProgramDiajukanPerUnitKerja();
        $pengajuan = $this->jumlahPengajuanPerUnitKerja();
        $realisasi = $this->jumlahRealisasiPerUnitKerja();
        $capaian = $this->capaianPerUnitKerja();

        return $this->perUnitKerja = collect($this->namaUnitKerja())
            ->map(fn (string $nama, int $unitKerjaId): RingkasanMonitoring => new RingkasanMonitoring(
                unitKerjaId: $unitKerjaId,
                label: $nama,
                pagu: (float) ($pagu[$unitKerjaId] ?? 0),
                pencairan: (float) ($pencairan[$unitKerjaId] ?? 0),
                sisaDikembalikan: (float) ($selisih[EnumStatusAnggaran::Sisa->value][$unitKerjaId] ?? 0),
                kekuranganDilunasi: (float) ($selisih[EnumStatusAnggaran::Kurang->value][$unitKerjaId] ?? 0),
                komitmen: (float) ($komitmen[$unitKerjaId] ?? 0),
                jumlahProgram: (int) ($program[$unitKerjaId] ?? 0),
                jumlahProgramDiajukan: (int) ($programDiajukan[$unitKerjaId] ?? 0),
                jumlahPengajuan: (int) ($pengajuan[$unitKerjaId] ?? 0),
                jumlahRealisasi: (int) ($realisasi['total'][$unitKerjaId] ?? 0),
                jumlahSelesai: (int) ($realisasi['selesai'][$unitKerjaId] ?? 0),
                capaian: $capaian[$unitKerjaId] ?? null,
            ))
            ->values();
    }

    /**
     * Penyerapan anggaran tiap bulan sepanjang tahun kerja beserta akumulasinya.
     * Bulan tanpa pencairan tetap tampil bernilai nol agar sumbu waktunya utuh.
     *
     * @return Collection<string, array{terserap: float, kumulatif: float}>
     */
    public function penyerapanPerBulan(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        $pencairan = $this->realisasiCairQuery()
            ->selectRaw($this->nominalPencairan().' as nominal')
            ->addSelect('realisasi_program_kerjas.dicairkan_at as tanggal')
            ->toBase()
            ->get()
            ->groupBy(fn (object $baris): string => Carbon::parse($baris->tanggal)->format('Y-m'))
            ->map(fn (Collection $kelompok): float => (float) $kelompok->sum('nominal'));

        $hasil = collect();
        $kumulatif = 0.0;

        foreach ($this->bulanTahunKerja($pencairan->keys()) as $kunci => $label) {
            $terserap = (float) ($pencairan[$kunci] ?? 0);
            $kumulatif += $terserap;

            $hasil->put($label, ['terserap' => $terserap, 'kumulatif' => $kumulatif]);
        }

        return $hasil;
    }

    /**
     * Distribusi anggaran yang terserap menurut kategori program kerjanya, terbesar
     * lebih dahulu. Kategori tanpa penyerapan tidak ikut tampil.
     *
     * @return Collection<string, float>
     */
    public function distribusiPerKategori(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        $perKategori = $this->realisasiCairQuery()
            ->groupBy('penawaran_program_kerjas.kategori_id')
            ->selectRaw('penawaran_program_kerjas.kategori_id as kategori_id, SUM('.$this->nominalPencairan().') as total')
            ->toBase()
            ->get()
            ->pluck('total', 'kategori_id');

        $nama = Kategori::query()
            ->whereIn('id', $perKategori->keys()->filter()->all())
            ->pluck('name', 'id');

        return $perKategori
            ->mapWithKeys(fn (mixed $total, mixed $kategoriId): array => [
                ($nama[$kategoriId] ?? 'Tanpa Kategori') => (float) $total,
            ])
            ->filter(fn (float $total): bool => $total > 0)
            ->sortDesc();
    }

    /**
     * Distribusi anggaran yang terserap menurut unit kerjanya, terbesar lebih dahulu.
     *
     * @return Collection<string, float>
     */
    public function distribusiPerUnitKerja(): Collection
    {
        return $this->perUnitKerja()
            ->mapWithKeys(fn (RingkasanMonitoring $ringkasan): array => [$ringkasan->label => $ringkasan->terserap()])
            ->filter(fn (float $total): bool => $total > 0)
            ->sortDesc();
    }

    /**
     * Capaian tiap program kerja yang sudah punya realisasi tuntas, terbesar lebih
     * dahulu. Dipakai grafik peringkat capaian.
     *
     * @return Collection<string, float>
     */
    public function capaianPerProgram(): Collection
    {
        return $this->barisProgram()
            ->filter(fn (array $baris): bool => $baris['capaian'] !== null)
            ->mapWithKeys(fn (array $baris): array => [$baris['program'] => (float) $baris['capaian']])
            ->sortDesc();
    }

    /**
     * Rincian per program kerja yang ditawarkan pada tahun kerja ini: berapa yang
     * diajukan unit kerja, berapa anggarannya terserap, dan berapa capaiannya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function barisProgram(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        $terserap = $this->pencairanPerPenawaran();
        $capaian = $this->capaianPerPenawaran();

        return PenawaranProgramKerja::query()
            ->with(['unitKerja', 'bidang', 'kategori', 'program'])
            ->where('tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('unit_kerja_id', $this->unitKerjaIds)
            ->where('is_active', true)
            ->withCount([
                'pengajuanProgramKerjas as jumlah_pengajuan' => fn (Builder $query): Builder => $query
                    ->where('status', '!=', EnumStatusPengajuan::Draft->value),
            ])
            ->withSum([
                'pengajuanProgramKerjas as total_alokasi' => fn (Builder $query): Builder => $query
                    ->whereIn('status', [EnumStatusPengajuan::Diajukan->value, EnumStatusPengajuan::Diterima->value]),
            ], 'alokasi_anggaran')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (PenawaranProgramKerja $penawaran): array => [$penawaran->getKey() => [
                'id' => $penawaran->getKey(),
                'program' => $penawaran->name,
                'unit_kerja' => $penawaran->unitKerja?->name ?? '-',
                'unit_kerja_id' => $penawaran->unit_kerja_id,
                'bidang' => $penawaran->bidang?->name,
                'bidang_id' => $penawaran->bidang_id,
                'kategori' => $penawaran->kategori?->name,
                'program_induk' => $penawaran->program?->name,
                'program_id' => $penawaran->program_id,
                'target' => $penawaran->target,
                'jumlah_pengajuan' => (int) $penawaran->jumlah_pengajuan,
                'alokasi' => (float) ($penawaran->total_alokasi ?? 0),
                'terserap' => (float) ($terserap[$penawaran->getKey()] ?? 0),
                'capaian' => $capaian[$penawaran->getKey()] ?? null,
            ]]);
    }

    /**
     * Nama unit kerja yang dibaca, id => nama, terurut nama.
     *
     * @return array<int, string>
     */
    public function namaUnitKerja(): array
    {
        return $this->namaUnitKerja ??= UnitKerja::query()
            ->whereIn('id', $this->unitKerjaIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Sumbu bulan tahun kerja: kunci `Y-m` => label "Jan 2026". Tahun kerja tanpa
     * rentang waktu jatuh kembali ke satu tahun kalender penuh sejak awal tahunnya.
     *
     * Pencairan yang jatuh di luar rentang tahun kerja tetap dirangkul agar tidak ada
     * penyerapan yang terhitung pada total tetapi hilang dari grafiknya.
     *
     * @param  Collection<int, string>  $bulanTerpakai  bulan pencairan dalam format `Y-m`
     * @return array<string, string>
     */
    protected function bulanTahunKerja(Collection $bulanTerpakai): array
    {
        $mulai = ($this->tahunKerja?->start_datetime ?? Carbon::create($this->tahunKerja?->tahunTarget() ?? Carbon::now()->year, 1, 1))->copy()->startOfMonth();
        $selesai = ($this->tahunKerja?->end_datetime ?? $mulai->copy()->addYear()->subMonth())->copy()->startOfMonth();

        if ($bulanTerpakai->isNotEmpty()) {
            $mulai = $mulai->min(Carbon::createFromFormat('Y-m', $bulanTerpakai->min())->startOfMonth());
            $selesai = $selesai->max(Carbon::createFromFormat('Y-m', $bulanTerpakai->max())->startOfMonth());
        }

        if ($selesai->lessThan($mulai)) {
            $selesai = $mulai->copy();
        }

        $bulan = [];

        for ($kursor = $mulai->copy(); $kursor->lessThanOrEqualTo($selesai); $kursor->addMonth()) {
            $bulan[$kursor->format('Y-m')] = $kursor->locale('id')->translatedFormat('M Y');
        }

        return $bulan;
    }

    /**
     * @return Collection<int, float> unit kerja id => pagu
     */
    protected function paguPerUnitKerja(): Collection
    {
        return PaguAnggaran::query()
            ->where('tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('unit_kerja_id', $this->unitKerjaIds)
            ->groupBy('unit_kerja_id')
            ->selectRaw('unit_kerja_id, SUM(amount) as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * Anggaran yang sudah disetujui namun belum dicairkan — sudah mengikat pagu
     * meski belum tampil di Buku Anggaran.
     *
     * @return Collection<int, float> unit kerja id => nominal
     */
    protected function komitmenPerUnitKerja(): Collection
    {
        return $this->realisasiQuery()
            ->whereNull('realisasi_program_kerjas.dicairkan_at')
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, SUM('.$this->nominalPencairan().') as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * @param  Builder<RealisasiProgramKerja>  $query
     * @return Collection<int, float> unit kerja id => nominal
     */
    protected function nominalPerUnitKerja(Builder $query): Collection
    {
        return $query
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, SUM('.$this->nominalPencairan().') as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * Selisih anggaran yang sudah dituntaskan, dipisah menurut arahnya: sisa yang
     * dikembalikan mengembalikan pagu, kekurangan yang dilunasi menggerusnya lagi.
     *
     * @return array<string, Collection<int, float>> nilai EnumStatusAnggaran => unit kerja id => nominal
     */
    protected function selisihPerUnitKerja(): array
    {
        $baris = $this->realisasiQuery()
            ->whereNotNull('realisasi_program_kerjas.penyelesaian_anggaran_at')
            ->whereIn('realisasi_program_kerjas.status_penyelesaian_anggaran', EnumStatusPenyelesaianAnggaran::nilaiSelesai())
            ->whereIn('realisasi_program_kerjas.status_anggaran', [EnumStatusAnggaran::Sisa->value, EnumStatusAnggaran::Kurang->value])
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id', 'realisasi_program_kerjas.status_anggaran')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, realisasi_program_kerjas.status_anggaran as status_anggaran, SUM(realisasi_program_kerjas.nominal_selisih_anggaran) as total')
            ->toBase()
            ->get()
            ->groupBy('status_anggaran')
            ->map(fn (Collection $kelompok): Collection => $kelompok->pluck('total', 'unit_kerja_id'));

        return [
            EnumStatusAnggaran::Sisa->value => $baris[EnumStatusAnggaran::Sisa->value] ?? collect(),
            EnumStatusAnggaran::Kurang->value => $baris[EnumStatusAnggaran::Kurang->value] ?? collect(),
        ];
    }

    /**
     * @return Collection<int, int> unit kerja id => jumlah program kerja ditawarkan
     */
    protected function jumlahProgramPerUnitKerja(): Collection
    {
        return PenawaranProgramKerja::query()
            ->where('tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('unit_kerja_id', $this->unitKerjaIds)
            ->where('is_active', true)
            ->groupBy('unit_kerja_id')
            ->selectRaw('unit_kerja_id, COUNT(*) as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * Program kerja yang sudah benar-benar diajukan unit kerjanya, dihitung sekali
     * meski diajukan lebih dari satu kali.
     *
     * @return Collection<int, int> unit kerja id => jumlah program kerja diajukan
     */
    protected function jumlahProgramDiajukanPerUnitKerja(): Collection
    {
        return $this->pengajuanQuery()
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, COUNT(DISTINCT pengajuan_program_kerjas.penawaran_program_kerja_id) as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * @return Collection<int, int> unit kerja id => jumlah pengajuan
     */
    protected function jumlahPengajuanPerUnitKerja(): Collection
    {
        return $this->pengajuanQuery()
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, COUNT(*) as total')
            ->toBase()
            ->get()
            ->pluck('total', 'unit_kerja_id');
    }

    /**
     * Jumlah realisasi berjalan dan yang sudah tuntas per unit kerja.
     *
     * @return array{total: Collection<int, int>, selesai: Collection<int, int>}
     */
    protected function jumlahRealisasiPerUnitKerja(): array
    {
        $baris = $this->realisasiQuery()
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, COUNT(*) as total, SUM(CASE WHEN realisasi_program_kerjas.status = ? THEN 1 ELSE 0 END) as selesai', [EnumStatusRealisasi::Selesai->value])
            ->toBase()
            ->get();

        return [
            'total' => $baris->pluck('total', 'unit_kerja_id'),
            'selesai' => $baris->pluck('selesai', 'unit_kerja_id'),
        ];
    }

    /**
     * Rata-rata capaian per unit kerja, dihitung dari realisasi terakhir tiap
     * pengajuan yang laporannya sudah tuntas — sejalan dengan
     * {@see PengajuanProgramKerja::persentaseKetercapaian()} yang juga membaca
     * realisasi terakhir sebuah pengajuan.
     *
     * @return array<int, float> unit kerja id => rata-rata capaian
     */
    protected function capaianPerUnitKerja(): array
    {
        return $this->capaianTerakhir()
            ->groupBy('unit_kerja_id')
            ->map(fn (Collection $kelompok): float => (float) $kelompok->avg('capaian'))
            ->all();
    }

    /**
     * Rata-rata capaian per program kerja yang ditawarkan.
     *
     * @return array<int, float> penawaran id => rata-rata capaian
     */
    protected function capaianPerPenawaran(): array
    {
        return $this->capaianTerakhir()
            ->groupBy('penawaran_id')
            ->map(fn (Collection $kelompok): float => (float) $kelompok->avg('capaian'))
            ->all();
    }

    /**
     * Capaian realisasi terakhir tiap pengajuan yang sudah tuntas, lengkap dengan
     * unit kerja dan program kerjanya. Pemilihan "terakhir" dikerjakan di PHP karena
     * jumlah barisnya sedikit dan sintaks window function berbeda antar basis data.
     *
     * @return Collection<int, array{unit_kerja_id: int, penawaran_id: int, capaian: float}>
     */
    protected function capaianTerakhir(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        return $this->realisasiQuery()
            ->where('realisasi_program_kerjas.status', EnumStatusRealisasi::Selesai->value)
            ->selectRaw('pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id, pengajuan_program_kerjas.penawaran_program_kerja_id as penawaran_id, realisasi_program_kerjas.pengajuan_program_kerja_id as pengajuan_id, realisasi_program_kerjas.persentase_ketercapaian as capaian, realisasi_program_kerjas.created_at as dicatat_pada, realisasi_program_kerjas.id as realisasi_id')
            ->toBase()
            ->get()
            ->groupBy('pengajuan_id')
            // Realisasi yang dicatat pada detik yang sama diputus oleh id-nya, agar
            // "realisasi terakhir" tidak bergantung pada urutan baris basis data.
            ->map(fn (Collection $kelompok): object => $kelompok
                ->sortBy(fn (object $baris): array => [$baris->dicatat_pada, $baris->realisasi_id])
                ->last())
            ->map(fn (object $baris): array => [
                'unit_kerja_id' => (int) $baris->unit_kerja_id,
                'penawaran_id' => (int) $baris->penawaran_id,
                'capaian' => (float) ($baris->capaian ?? 0),
            ])
            ->values();
    }

    /**
     * @return Collection<int, float> penawaran id => anggaran yang dicairkan
     */
    protected function pencairanPerPenawaran(): Collection
    {
        return $this->realisasiCairQuery()
            ->groupBy('pengajuan_program_kerjas.penawaran_program_kerja_id')
            ->selectRaw('pengajuan_program_kerjas.penawaran_program_kerja_id as penawaran_id, SUM('.$this->nominalPencairan().') as total')
            ->toBase()
            ->get()
            ->pluck('total', 'penawaran_id');
    }

    /**
     * Pengajuan yang benar-benar diajukan unit kerja pada tahun kerja ini; draf tidak
     * dihitung karena belum menjadi komitmen apa pun.
     *
     * @return Builder<PengajuanProgramKerja>
     */
    protected function pengajuanQuery(): Builder
    {
        return PengajuanProgramKerja::query()
            ->join('penawaran_program_kerjas', 'penawaran_program_kerjas.id', '=', 'pengajuan_program_kerjas.penawaran_program_kerja_id')
            ->where('penawaran_program_kerjas.tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('pengajuan_program_kerjas.unit_kerja_id', $this->unitKerjaIds)
            ->where('pengajuan_program_kerjas.status', '!=', EnumStatusPengajuan::Draft->value);
    }

    /**
     * Realisasi yang benar-benar berjalan pada tahun kerja & unit kerja yang dibaca.
     * Draf, yang ditolak, dan yang dibatalkan tidak pernah menyerap anggaran.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        return RealisasiProgramKerja::query()
            ->join('pengajuan_program_kerjas', 'pengajuan_program_kerjas.id', '=', 'realisasi_program_kerjas.pengajuan_program_kerja_id')
            ->join('penawaran_program_kerjas', 'penawaran_program_kerjas.id', '=', 'pengajuan_program_kerjas.penawaran_program_kerja_id')
            ->where('penawaran_program_kerjas.tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('pengajuan_program_kerjas.unit_kerja_id', $this->unitKerjaIds)
            ->whereNotIn('realisasi_program_kerjas.status', [
                EnumStatusRealisasi::Draft->value,
                EnumStatusRealisasi::Ditolak->value,
                EnumStatusRealisasi::Dibatalkan->value,
            ]);
    }

    /**
     * Realisasi yang anggarannya sudah cair — dasar seluruh angka penyerapan.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiCairQuery(): Builder
    {
        return $this->realisasiQuery()->whereNotNull('realisasi_program_kerjas.dicairkan_at');
    }

    /**
     * Nominal yang dicairkan untuk sebuah realisasi dalam bentuk ekspresi SQL, meniru
     * {@see RealisasiProgramKerja::nominalPencairan()}: nominal yang disetujui
     * verifikator, atau nominal yang diajukan bila penetapannya dilewati.
     */
    protected function nominalPencairan(): string
    {
        return 'COALESCE(realisasi_program_kerjas.nominal_disetujui, realisasi_program_kerjas.nominal_diajukan, realisasi_program_kerjas.anggaran_digunakan, 0)';
    }

    protected function tahunKerjaId(): ?int
    {
        return $this->tahunKerja?->getKey();
    }
}

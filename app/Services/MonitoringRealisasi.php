<?php

namespace App\Services;

use App\Enums\EnumStatusRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka pemantauan realisasi program kerja beberapa unit kerja: berapa yang masih
 * berjalan, berapa yang sudah tuntas, dan berapa anggaran yang mengalir pada tiap
 * tahapnya.
 *
 * Berbeda dengan {@see MonitoringAnggaran} yang selalu terikat satu tahun kerja,
 * cakupan di sini boleh dibiarkan lintas tahun ({@see $tahunKerjaId} bernilai null)
 * sehingga realisasi tahun-tahun sebelumnya tetap dapat ditelusuri walaupun menu
 * harian sudah berpindah ke tahun kerja yang baru ({@see KonteksProgramKerja}).
 *
 * Realisasi berstatus draf tidak pernah ikut dihitung: draf belum menjadi pekerjaan
 * yang berjalan maupun komitmen anggaran apa pun. Realisasi yang ditolak maupun
 * dibatalkan tetap tampil pada sebaran status — supaya terlihat berapa yang kandas —
 * tetapi tidak menyumbang nominal anggaran.
 *
 * Pengelompokan bulan dilakukan di PHP, bukan lewat fungsi tanggal basis data, agar
 * hasilnya sama pada MySQL maupun SQLite.
 */
class MonitoringRealisasi
{
    /**
     * @var Collection<int, RingkasanRealisasi>|null
     */
    private ?Collection $perUnitKerja = null;

    /**
     * @var array<int, string>|null Nama unit kerja yang dibaca, id => nama.
     */
    private ?array $namaUnitKerja = null;

    private ?TahunKerja $tahunKerja = null;

    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang dibaca.
     * @param  int|null  $tahunKerjaId  Null berarti seluruh tahun kerja.
     */
    public function __construct(
        public readonly array $unitKerjaIds,
        public readonly ?int $tahunKerjaId = null,
    ) {}

    /**
     * @param  array<int, int>  $unitKerjaIds
     */
    public static function untukUnits(array $unitKerjaIds, ?int $tahunKerjaId = null): self
    {
        return new self(array_values(array_map(intval(...), $unitKerjaIds)), $tahunKerjaId);
    }

    /**
     * Cakupan kosong: tanpa satu pun unit kerja, seluruh angka bernilai nol tanpa
     * menyentuh basis data.
     */
    public function kosong(): bool
    {
        return $this->unitKerjaIds === [];
    }

    /**
     * Realisasi yang dipantau, siap dipakai sebagai kueri tabel Filament. Memakai
     * `whereHas` alih-alih join agar barisnya tetap model utuh tanpa kolom bentrok.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    public function queryTabel(): Builder
    {
        $query = RealisasiProgramKerja::query()
            ->with([
                'pengajuanProgramKerja.unitKerja',
                'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja',
            ])
            ->where('status', '!=', EnumStatusRealisasi::Draft->value);

        if ($this->kosong()) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas(
            'pengajuanProgramKerja',
            fn (Builder $pengajuan): Builder => $pengajuan->whereIn('unit_kerja_id', $this->unitKerjaIds),
        );

        if ($this->tahunKerjaId !== null) {
            $query->whereHas(
                'pengajuanProgramKerja.penawaranProgramKerja',
                fn (Builder $penawaran): Builder => $penawaran->where('tahun_kerja_id', $this->tahunKerjaId),
            );
        }

        return $query;
    }

    /**
     * Angka gabungan seluruh unit kerja yang dibaca.
     */
    public function ringkasan(string $label = 'Seluruh Unit Kerja'): RingkasanRealisasi
    {
        if ($this->kosong()) {
            return RingkasanRealisasi::kosong(label: $label);
        }

        if (count($this->unitKerjaIds) === 1) {
            return $this->perUnitKerja()->first() ?? RingkasanRealisasi::kosong($this->unitKerjaIds[0], $label);
        }

        return RingkasanRealisasi::gabung($this->perUnitKerja(), label: $label);
    }

    /**
     * Angka tiap unit kerja, terurut mengikuti nama unitnya. Unit yang belum punya
     * realisasi apa pun tetap muncul dengan angka nol agar tidak hilang dari rekap.
     *
     * @return Collection<int, RingkasanRealisasi>
     */
    public function perUnitKerja(): Collection
    {
        if ($this->perUnitKerja !== null) {
            return $this->perUnitKerja;
        }

        if ($this->kosong()) {
            return $this->perUnitKerja = collect();
        }

        $agregat = $this->agregatPerUnitKerja();

        return $this->perUnitKerja = collect($this->namaUnitKerja())
            ->map(function (string $nama, int $unitKerjaId) use ($agregat): RingkasanRealisasi {
                $baris = $agregat[$unitKerjaId] ?? null;

                if ($baris === null) {
                    return RingkasanRealisasi::kosong($unitKerjaId, $nama);
                }

                return new RingkasanRealisasi(
                    unitKerjaId: $unitKerjaId,
                    label: $nama,
                    jumlah: (int) $baris->jumlah,
                    berjalan: (int) $baris->berjalan,
                    selesai: (int) $baris->selesai,
                    batal: (int) $baris->batal,
                    sudahCair: (int) $baris->sudah_cair,
                    disetujui: (float) $baris->disetujui,
                    dicairkan: (float) $baris->dicairkan,
                    dilaporkan: (float) $baris->dilaporkan,
                    capaian: $baris->capaian === null ? null : (float) $baris->capaian,
                );
            })
            ->values();
    }

    /**
     * Jumlah realisasi tiap status, urut mengikuti alur statusnya. Status tanpa satu
     * pun realisasi tidak ikut tampil agar grafiknya tetap terbaca.
     *
     * @return Collection<string, int>
     */
    public function sebaranStatus(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        $jumlah = $this->realisasiQuery()
            ->groupBy('realisasi_program_kerjas.status')
            ->selectRaw('realisasi_program_kerjas.status as status, COUNT(*) as jumlah')
            ->toBase()
            ->get()
            ->pluck('jumlah', 'status');

        return collect(EnumStatusRealisasi::cases())
            ->mapWithKeys(fn (EnumStatusRealisasi $status): array => [
                $status->getLabel() => (int) ($jumlah[$status->value] ?? 0),
            ])
            ->filter(fn (int $total): bool => $total > 0);
    }

    /**
     * Aliran anggaran realisasi sepanjang sumbu waktu: per bulan bila satu tahun kerja
     * dipantau, per tahun kerja bila cakupannya lintas tahun. Keduanya memakai bulan
     * pencairan sebagai penanda waktu, karena saat itulah anggaran benar-benar keluar.
     *
     * @return Collection<string, array{dicairkan: float, dilaporkan: float}>
     */
    public function anggaranPerPeriode(): Collection
    {
        if ($this->kosong()) {
            return collect();
        }

        return $this->tahunKerjaId !== null
            ? $this->anggaranPerBulan()
            : $this->anggaranPerTahunKerja();
    }

    /**
     * Sebutan satuan waktu grafik anggaran, mengikuti cakupan yang sedang dipantau.
     */
    public function labelPeriode(): string
    {
        return $this->tahunKerjaId !== null ? 'bulan' : 'tahun kerja';
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

    public function tahunKerja(): ?TahunKerja
    {
        if ($this->tahunKerjaId === null) {
            return null;
        }

        return $this->tahunKerja ??= TahunKerja::find($this->tahunKerjaId);
    }

    /**
     * Seluruh angka tiap unit kerja dalam satu kali kueri agregat.
     *
     * @return Collection<int, object>
     */
    protected function agregatPerUnitKerja(): Collection
    {
        $berjalan = array_column(EnumStatusRealisasi::berjalan(), 'value');
        $batal = [EnumStatusRealisasi::Ditolak->value, EnumStatusRealisasi::Dibatalkan->value];
        $selesai = EnumStatusRealisasi::Selesai->value;

        $nominal = $this->nominalPencairan();
        $dilaporkan = 'CASE WHEN realisasi_program_kerjas.laporan_diserahkan_at IS NULL THEN 0 ELSE COALESCE(realisasi_program_kerjas.anggaran_digunakan, 0) END';

        $kolom = implode(', ', [
            'pengajuan_program_kerjas.unit_kerja_id as unit_kerja_id',
            'COUNT(*) as jumlah',
            'SUM(CASE WHEN realisasi_program_kerjas.status IN ('.static::placeholders($berjalan).') THEN 1 ELSE 0 END) as berjalan',
            'SUM(CASE WHEN realisasi_program_kerjas.status = ? THEN 1 ELSE 0 END) as selesai',
            'SUM(CASE WHEN realisasi_program_kerjas.status IN ('.static::placeholders($batal).') THEN 1 ELSE 0 END) as batal',
            'SUM(CASE WHEN realisasi_program_kerjas.dicairkan_at IS NULL THEN 0 ELSE 1 END) as sudah_cair',
            'SUM(CASE WHEN realisasi_program_kerjas.status IN ('.static::placeholders($batal).') THEN 0 ELSE '.$nominal.' END) as disetujui',
            'SUM(CASE WHEN realisasi_program_kerjas.dicairkan_at IS NULL THEN 0 ELSE '.$nominal.' END) as dicairkan',
            'SUM('.$dilaporkan.') as dilaporkan',
            'AVG(CASE WHEN realisasi_program_kerjas.status = ? THEN realisasi_program_kerjas.persentase_ketercapaian END) as capaian',
        ]);

        return $this->realisasiQuery()
            ->groupBy('pengajuan_program_kerjas.unit_kerja_id')
            ->selectRaw($kolom, [...$berjalan, $selesai, ...$batal, ...$batal, $selesai])
            ->toBase()
            ->get()
            ->keyBy('unit_kerja_id');
    }

    /**
     * Anggaran yang cair tiap bulan sepanjang tahun kerja yang dipantau. Bulan tanpa
     * pencairan tetap tampil bernilai nol agar sumbu waktunya utuh, dan pencairan di
     * luar rentang tahun kerja tetap dirangkul agar tidak ada yang hilang dari grafik.
     *
     * @return Collection<string, array{dicairkan: float, dilaporkan: float}>
     */
    protected function anggaranPerBulan(): Collection
    {
        $baris = $this->barisPencairan()
            ->groupBy(fn (object $baris): string => Carbon::parse($baris->tanggal)->format('Y-m'));

        $hasil = collect();

        foreach ($this->bulanTahunKerja($baris->keys()) as $kunci => $label) {
            $kelompok = $baris->get($kunci, collect());

            $hasil->put($label, [
                'dicairkan' => (float) $kelompok->sum('dicairkan'),
                'dilaporkan' => (float) $kelompok->sum('dilaporkan'),
            ]);
        }

        return $hasil;
    }

    /**
     * Anggaran yang cair pada tiap tahun kerja, tahun terlama lebih dahulu, sehingga
     * tahun berjalan dapat dibandingkan dengan tahun-tahun sebelumnya.
     *
     * @return Collection<string, array{dicairkan: float, dilaporkan: float}>
     */
    protected function anggaranPerTahunKerja(): Collection
    {
        $nominal = $this->nominalPencairan();
        $dilaporkan = 'CASE WHEN realisasi_program_kerjas.laporan_diserahkan_at IS NULL THEN 0 ELSE COALESCE(realisasi_program_kerjas.anggaran_digunakan, 0) END';

        $baris = $this->realisasiQuery()
            ->whereNotNull('realisasi_program_kerjas.dicairkan_at')
            ->groupBy('penawaran_program_kerjas.tahun_kerja_id')
            ->selectRaw('penawaran_program_kerjas.tahun_kerja_id as tahun_kerja_id, SUM('.$nominal.') as dicairkan, SUM('.$dilaporkan.') as dilaporkan')
            ->toBase()
            ->get()
            ->keyBy('tahun_kerja_id');

        if ($baris->isEmpty()) {
            return collect();
        }

        return TahunKerja::query()
            ->whereIn('id', $baris->keys()->all())
            ->orderBy('start_datetime')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (TahunKerja $tahunKerja): array => [
                $tahunKerja->name => [
                    'dicairkan' => (float) ($baris[$tahunKerja->getKey()]->dicairkan ?? 0),
                    'dilaporkan' => (float) ($baris[$tahunKerja->getKey()]->dilaporkan ?? 0),
                ],
            ]);
    }

    /**
     * Baris pencairan mentah beserta tanggalnya, dipakai pengelompokan bulan di PHP.
     *
     * @return Collection<int, object>
     */
    protected function barisPencairan(): Collection
    {
        $dilaporkan = 'CASE WHEN realisasi_program_kerjas.laporan_diserahkan_at IS NULL THEN 0 ELSE COALESCE(realisasi_program_kerjas.anggaran_digunakan, 0) END';

        return $this->realisasiQuery()
            ->whereNotNull('realisasi_program_kerjas.dicairkan_at')
            ->selectRaw($this->nominalPencairan().' as dicairkan, '.$dilaporkan.' as dilaporkan, realisasi_program_kerjas.dicairkan_at as tanggal')
            ->toBase()
            ->get();
    }

    /**
     * Sumbu bulan tahun kerja: kunci `Y-m` => label "Jan 2026". Tahun kerja tanpa
     * rentang waktu jatuh kembali ke satu tahun kalender penuh sejak awal tahunnya.
     *
     * @param  Collection<int, string>  $bulanTerpakai  bulan pencairan dalam format `Y-m`
     * @return array<string, string>
     */
    protected function bulanTahunKerja(Collection $bulanTerpakai): array
    {
        $tahunKerja = $this->tahunKerja();

        $mulai = ($tahunKerja?->start_datetime ?? Carbon::create($tahunKerja?->tahunTarget() ?? Carbon::now()->year, 1, 1))->copy()->startOfMonth();
        $selesai = ($tahunKerja?->end_datetime ?? $mulai->copy()->addYear()->subMonth())->copy()->startOfMonth();

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
     * Realisasi yang dipantau, dirangkai lewat join agar seluruh angkanya bisa
     * dijumlahkan langsung oleh basis data. Draf tidak pernah ikut.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        $query = RealisasiProgramKerja::query()
            ->join('pengajuan_program_kerjas', 'pengajuan_program_kerjas.id', '=', 'realisasi_program_kerjas.pengajuan_program_kerja_id')
            ->join('penawaran_program_kerjas', 'penawaran_program_kerjas.id', '=', 'pengajuan_program_kerjas.penawaran_program_kerja_id')
            ->whereIn('pengajuan_program_kerjas.unit_kerja_id', $this->unitKerjaIds)
            ->where('realisasi_program_kerjas.status', '!=', EnumStatusRealisasi::Draft->value);

        if ($this->tahunKerjaId !== null) {
            $query->where('penawaran_program_kerjas.tahun_kerja_id', $this->tahunKerjaId);
        }

        return $query;
    }

    /**
     * Nominal yang dicairkan untuk sebuah realisasi dalam bentuk ekspresi SQL, meniru
     * {@see RealisasiProgramKerja::nominalPencairan()} — sama persis dengan yang
     * dipakai {@see MonitoringAnggaran} agar kedua halaman monitoring tidak pernah
     * menyebut angka yang berbeda untuk realisasi yang sama.
     */
    protected function nominalPencairan(): string
    {
        return 'COALESCE(realisasi_program_kerjas.nominal_disetujui, realisasi_program_kerjas.nominal_diajukan, realisasi_program_kerjas.anggaran_digunakan, 0)';
    }

    /**
     * Deretan tanda tanya sebanyak nilai yang diikat, mis. "?, ?, ?".
     *
     * @param  array<int, mixed>  $nilai
     */
    protected static function placeholders(array $nilai): string
    {
        return implode(', ', array_fill(0, count($nilai), '?'));
    }
}

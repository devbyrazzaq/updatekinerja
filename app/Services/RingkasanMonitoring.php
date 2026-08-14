<?php

namespace App\Services;

/**
 * Angka monitoring satu cakupan — bisa satu unit kerja, bisa gabungan beberapa unit —
 * pada satu tahun kerja. Nominalnya disimpan mentah (pencairan dan penyelesaian
 * selisih dipisah) sehingga penyerapan tetap bisa diturunkan dengan rumus yang sama
 * dengan {@see BukuAnggaran}: penyerapan adalah pagu dikurangi saldo bukunya.
 */
final readonly class RingkasanMonitoring
{
    public function __construct(
        public ?int $unitKerjaId,
        public string $label,
        public float $pagu,
        public float $pencairan,
        public float $sisaDikembalikan,
        public float $kekuranganDilunasi,
        public float $komitmen,
        public int $jumlahProgram,
        public int $jumlahProgramDiajukan,
        public int $jumlahPengajuan,
        public int $jumlahRealisasi,
        public int $jumlahSelesai,
        public ?float $capaian,
    ) {}

    /**
     * Cakupan tanpa data sama sekali, dipakai saat tahun kerja belum ditetapkan.
     */
    public static function kosong(?int $unitKerjaId = null, string $label = '-'): self
    {
        return new self(
            unitKerjaId: $unitKerjaId,
            label: $label,
            pagu: 0.0,
            pencairan: 0.0,
            sisaDikembalikan: 0.0,
            kekuranganDilunasi: 0.0,
            komitmen: 0.0,
            jumlahProgram: 0,
            jumlahProgramDiajukan: 0,
            jumlahPengajuan: 0,
            jumlahRealisasi: 0,
            jumlahSelesai: 0,
            capaian: null,
        );
    }

    /**
     * Menjumlahkan beberapa ringkasan menjadi satu. Capaiannya dirata-rata dengan
     * bobot jumlah realisasi selesai, supaya unit dengan sedikit kegiatan tidak
     * menarik angka gabungan sekuat unit yang banyak kegiatannya.
     *
     * @param  iterable<int, self>  $ringkasan
     */
    public static function gabung(iterable $ringkasan, ?int $unitKerjaId = null, string $label = 'Seluruh Unit Kerja'): self
    {
        $daftar = collect($ringkasan);

        $berbobot = $daftar->filter(fn (self $item): bool => $item->capaian !== null && $item->jumlahSelesai > 0);
        $bobot = (int) $berbobot->sum(fn (self $item): int => $item->jumlahSelesai);

        return new self(
            unitKerjaId: $unitKerjaId,
            label: $label,
            pagu: (float) $daftar->sum(fn (self $item): float => $item->pagu),
            pencairan: (float) $daftar->sum(fn (self $item): float => $item->pencairan),
            sisaDikembalikan: (float) $daftar->sum(fn (self $item): float => $item->sisaDikembalikan),
            kekuranganDilunasi: (float) $daftar->sum(fn (self $item): float => $item->kekuranganDilunasi),
            komitmen: (float) $daftar->sum(fn (self $item): float => $item->komitmen),
            jumlahProgram: (int) $daftar->sum(fn (self $item): int => $item->jumlahProgram),
            jumlahProgramDiajukan: (int) $daftar->sum(fn (self $item): int => $item->jumlahProgramDiajukan),
            jumlahPengajuan: (int) $daftar->sum(fn (self $item): int => $item->jumlahPengajuan),
            jumlahRealisasi: (int) $daftar->sum(fn (self $item): int => $item->jumlahRealisasi),
            jumlahSelesai: (int) $daftar->sum(fn (self $item): int => $item->jumlahSelesai),
            capaian: $bobot === 0
                ? null
                : (float) $berbobot->sum(fn (self $item): float => $item->capaian * $item->jumlahSelesai) / $bobot,
        );
    }

    /**
     * Anggaran yang benar-benar terserap: yang dicairkan, ditambah kekurangan yang
     * dilunasi unit kerja, dikurangi sisa yang sudah dikembalikan. Selisih yang masih
     * menunggu Biro Keuangan belum ikut dihitung, persis seperti Buku Anggaran.
     */
    public function terserap(): float
    {
        return $this->pencairan + $this->kekuranganDilunasi - $this->sisaDikembalikan;
    }

    /**
     * Pagu yang belum terserap. Bisa negatif bila pencairan melampaui pagu.
     */
    public function sisaPagu(): float
    {
        return $this->pagu - $this->terserap();
    }

    /**
     * Porsi pagu yang sudah terserap. Null bila pagunya belum ditetapkan, karena
     * persentase tanpa pembanding tidak berarti apa-apa.
     */
    public function persentasePenyerapan(): ?float
    {
        return $this->pagu > 0 ? $this->terserap() / $this->pagu * 100 : null;
    }

    /**
     * Porsi program kerja yang sudah diajukan unit kerja dari yang ditawarkan.
     */
    public function persentasePelaksanaan(): ?float
    {
        return $this->jumlahProgram > 0 ? $this->jumlahProgramDiajukan / $this->jumlahProgram * 100 : null;
    }

    /**
     * Porsi realisasi yang laporannya sudah tuntas dari seluruh realisasi berjalan.
     */
    public function persentasePenyelesaian(): ?float
    {
        return $this->jumlahRealisasi > 0 ? $this->jumlahSelesai / $this->jumlahRealisasi * 100 : null;
    }

    /**
     * Bentuk array untuk baris tabel Filament yang membaca record sebagai array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_kerja_id' => $this->unitKerjaId,
            'label' => $this->label,
            'pagu' => $this->pagu,
            'pencairan' => $this->pencairan,
            'sisa_dikembalikan' => $this->sisaDikembalikan,
            'kekurangan_dilunasi' => $this->kekuranganDilunasi,
            'komitmen' => $this->komitmen,
            'terserap' => $this->terserap(),
            'sisa_pagu' => $this->sisaPagu(),
            'persentase_penyerapan' => $this->persentasePenyerapan(),
            'jumlah_program' => $this->jumlahProgram,
            'jumlah_program_diajukan' => $this->jumlahProgramDiajukan,
            'persentase_pelaksanaan' => $this->persentasePelaksanaan(),
            'jumlah_pengajuan' => $this->jumlahPengajuan,
            'jumlah_realisasi' => $this->jumlahRealisasi,
            'jumlah_selesai' => $this->jumlahSelesai,
            'capaian' => $this->capaian,
        ];
    }
}

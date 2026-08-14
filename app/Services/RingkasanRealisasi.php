<?php

namespace App\Services;

/**
 * Angka pemantauan realisasi satu cakupan — bisa satu unit kerja, bisa gabungan
 * beberapa unit — pada satu tahun kerja atau sepanjang seluruh tahun kerja.
 *
 * Berbeda dengan {@see RingkasanMonitoring} yang berbicara tentang pagu dan
 * penyerapannya, ringkasan ini berbicara tentang kegiatannya: berapa realisasi yang
 * masih berjalan, berapa yang sudah tuntas, dan berapa anggaran yang mengalir pada
 * tiap tahapnya (disetujui, dicairkan, lalu dipertanggungjawabkan lewat laporan).
 */
final readonly class RingkasanRealisasi
{
    public function __construct(
        public ?int $unitKerjaId,
        public string $label,
        public int $jumlah,
        public int $berjalan,
        public int $selesai,
        public int $batal,
        public int $sudahCair,
        public float $disetujui,
        public float $dicairkan,
        public float $dilaporkan,
        public ?float $capaian,
    ) {}

    /**
     * Cakupan tanpa satu pun realisasi.
     */
    public static function kosong(?int $unitKerjaId = null, string $label = '-'): self
    {
        return new self(
            unitKerjaId: $unitKerjaId,
            label: $label,
            jumlah: 0,
            berjalan: 0,
            selesai: 0,
            batal: 0,
            sudahCair: 0,
            disetujui: 0.0,
            dicairkan: 0.0,
            dilaporkan: 0.0,
            capaian: null,
        );
    }

    /**
     * Menjumlahkan beberapa ringkasan menjadi satu. Capaiannya dirata-rata dengan
     * bobot jumlah realisasi selesai, sejalan dengan {@see RingkasanMonitoring::gabung()}.
     *
     * @param  iterable<int, self>  $ringkasan
     */
    public static function gabung(iterable $ringkasan, ?int $unitKerjaId = null, string $label = 'Seluruh Unit Kerja'): self
    {
        $daftar = collect($ringkasan);

        $berbobot = $daftar->filter(fn (self $item): bool => $item->capaian !== null && $item->selesai > 0);
        $bobot = (int) $berbobot->sum(fn (self $item): int => $item->selesai);

        return new self(
            unitKerjaId: $unitKerjaId,
            label: $label,
            jumlah: (int) $daftar->sum(fn (self $item): int => $item->jumlah),
            berjalan: (int) $daftar->sum(fn (self $item): int => $item->berjalan),
            selesai: (int) $daftar->sum(fn (self $item): int => $item->selesai),
            batal: (int) $daftar->sum(fn (self $item): int => $item->batal),
            sudahCair: (int) $daftar->sum(fn (self $item): int => $item->sudahCair),
            disetujui: (float) $daftar->sum(fn (self $item): float => $item->disetujui),
            dicairkan: (float) $daftar->sum(fn (self $item): float => $item->dicairkan),
            dilaporkan: (float) $daftar->sum(fn (self $item): float => $item->dilaporkan),
            capaian: $bobot === 0
                ? null
                : (float) $berbobot->sum(fn (self $item): float => $item->capaian * $item->selesai) / $bobot,
        );
    }

    /**
     * Anggaran yang sudah disetujui verifikator tetapi belum diserahkan ke unit kerja.
     */
    public function menungguCair(): float
    {
        return max(0.0, $this->disetujui - $this->dicairkan);
    }

    /**
     * Realisasi yang sudah cair namun laporannya belum tuntas — pekerjaan yang masih
     * menggantung di tangan unit kerja maupun verifikator laporan.
     */
    public function belumDipertanggungjawabkan(): int
    {
        return max(0, $this->sudahCair - $this->selesai);
    }

    /**
     * Porsi realisasi yang sudah tuntas dari seluruh realisasi yang berjalan maupun
     * pernah berjalan. Realisasi yang ditolak/dibatalkan tidak ikut menjadi pembagi
     * karena tidak pernah menjadi pekerjaan yang harus dituntaskan.
     */
    public function persentaseSelesai(): ?float
    {
        $pembagi = $this->jumlah - $this->batal;

        return $pembagi > 0 ? $this->selesai / $pembagi * 100 : null;
    }

    /**
     * Porsi anggaran cair yang sudah dipertanggungjawabkan lewat laporan realisasi.
     */
    public function persentaseDilaporkan(): ?float
    {
        return $this->dicairkan > 0 ? $this->dilaporkan / $this->dicairkan * 100 : null;
    }

    /**
     * Bentuk array untuk baris tabel maupun kartu ringkasan pada blade.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_kerja_id' => $this->unitKerjaId,
            'label' => $this->label,
            'jumlah' => $this->jumlah,
            'berjalan' => $this->berjalan,
            'selesai' => $this->selesai,
            'batal' => $this->batal,
            'sudah_cair' => $this->sudahCair,
            'disetujui' => $this->disetujui,
            'dicairkan' => $this->dicairkan,
            'dilaporkan' => $this->dilaporkan,
            'menunggu_cair' => $this->menungguCair(),
            'persentase_selesai' => $this->persentaseSelesai(),
            'persentase_dilaporkan' => $this->persentaseDilaporkan(),
            'capaian' => $this->capaian,
        ];
    }
}

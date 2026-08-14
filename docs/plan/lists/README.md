# Indeks Spec Resource — urutan pengerjaan

FK harus menunjuk resource yang dikerjakan lebih dulu (urutan dependency).

## Master Data
1. [Periode](master-data/periode.md)
2. [Tahun Kerja](master-data/tahun-kerja.md) — FK Periode
3. [Kategori](master-data/kategori.md)
4. [Bidang](master-data/bidang.md)
5. [Unit Kerja](master-data/unit-kerja.md)
6. [Program](master-data/program.md)

## Anggaran
7. [Rekening](anggaran/rekening.md)
8. [Pagu Anggaran](anggaran/pagu-anggaran.md) — FK Tahun Kerja, Unit Kerja

## Program Kerja
8b. Kelompok Acuan — resource `KelompokAcuan` (grup acuan 5 tahunan, mis. "Program Kerja 2025 - 2030"); punya RelationManager daftar acuan. Kelompok aktif jadi default filter menu Acuan.
9. [Acuan Program Kerja](program-kerja/acuan-program-kerja.md) — FK Kelompok Acuan + Unit/Bidang/Kategori/Program/Rekening + AcuanTarget
10. [Penawaran Program Kerja](program-kerja/penawaran-program-kerja.md) — FK Acuan, Tahun Kerja, + master di atas

## Pemasukan
10b. Pemasukan Unit — resource `Pemasukan` (pencatatan pendapatan per unit; FK Unit Kerja + salah satu Pengajuan/Realisasi Program Kerja via kolom `sumber`). Export saja.

### Pengembangan lanjutan — [Jenis Waktu & Verifikasi Pemasukan](pemasukan/README.md)
10c. [Pemasukan (perubahan)](pemasukan/pemasukan.md) — jenis waktu 1 hari/rentang, status alur, aksi Ajukan & Unggah Bukti Tanda Terima
10d. [Verifikasi Pemasukan](pemasukan/verifikasi-pemasukan.md) — grup baru: Verifikasi Rektor, Wakil Rektor, Biro Keuangan atas `Pemasukan`

## Pelaksanaan
11. [Daftar Program Kerja](pelaksanaan/daftar-program-kerja.md) — view Penawaran (read-only)
12. [Pengajuan Program Kerja](pelaksanaan/pengajuan-program-kerja.md) — FK Penawaran
13. [Realisasi Program Kerja](pelaksanaan/realisasi-program-kerja.md) — FK Pengajuan

## Verifikasi
14. [Verifikasi Pengajuan (Tahap 1)](verifikasi/verifikasi-pengajuan.md) — scoping Pengajuan
15. [Verifikasi Rektor](verifikasi/verifikasi-rektor.md) — scoping Realisasi
16. [Verifikasi Wakil Rektor](verifikasi/verifikasi-wakil-rektor.md)
17. [Verifikasi Biro Keuangan](verifikasi/verifikasi-biro-keuangan.md) — antrean proses pencairan
17b. Jadwal Pencairan — resource `JadwalPencairan` (buku jadwal + aksi Tandai Dicairkan)
18. [Verifikasi Laporan](verifikasi/verifikasi-laporan.md)

## Manajemen Akses
19. [Pengguna](manajemen-akses/pengguna.md) — User dasar (username)

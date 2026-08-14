# Rencana — Jenis Waktu & Verifikasi Pemasukan Unit

Dua perubahan pada modul Pemasukan Unit:

1. **Jenis waktu** — pemasukan bisa berlangsung **1 hari** atau **rentang waktu**
   (menentukan sampai kapannya).
2. **Alur verifikasi** — Rektor → Wakil Rektor → Biro Keuangan, lalu unit kerja
   mengunggah **bukti tanda terima**; setelah itu barulah pemasukan berstatus **Valid**.

Spec detail:

- [`pemasukan.md`](pemasukan.md) — perubahan pada Resource `Pemasukan` (form, tabel,
  infolist, aksi unit kerja).
- [`verifikasi-pemasukan.md`](verifikasi-pemasukan.md) — 3 Resource verifikasi baru
  pada grup navigasi `Verifikasi Pemasukan`.

---

## Keputusan Arsitektur (dikonfirmasi user)

| # | Keputusan | Konsekuensi |
| - | --------- | ----------- |
| 1 | Grup navigasi **baru** `Verifikasi Pemasukan` berisi 3 Resource terpisah | Antrean pemasukan tidak tercampur dengan realisasi; `HasVerificationStageScopes` + `HasVerificationStageTabs` dipakai ulang apa adanya |
| 2 | Upload bukti tanda terima **langsung** membuat pemasukan Valid | Tidak ada tahap "Verifikasi Bukti"; alur berakhir di unit kerja |
| 3 | **Hanya** pemasukan berstatus Valid yang masuk Buku Anggaran | `BukuAnggaran::barisPemasukan()` disaring; widget & export ikut menyesuaikan |
| 4 | Verifikator **tidak** mengubah nominal — hanya Setujui / Revisi / Tolak | Tidak perlu kolom `nominal_disetujui`/`penentu_nominal_id`; aksi Setujui cukup konfirmasi + catatan opsional |
| 5 | Kolom **`user_id` (pengaju) ditambahkan** ke tabel `pemasukans` | Notifikasi revisi/penolakan/permintaan bukti dikirim tepat ke pencatat pemasukan, bukan disebar ke seluruh unit |
| 6 | Batas berkas bukti diatur **dinamis lewat halaman Pengaturan Sistem** | Bukan nilai hardcode: tambah konstanta + `DEFAULTS` di `Setting`, accessor bertipe, section baru di `PengaturanSistem`, dan otomatis terbawa `SettingSeeder` |

---

## Pola yang Diikuti (sudah ada di sistem)

Alur verifikasi pemasukan **mencontoh persis** alur Verifikasi Realisasi yang sudah
berjalan, sehingga hampir seluruh infrastrukturnya tinggal ditiru:

| Kebutuhan | Acuan yang sudah ada |
| --------- | -------------------- |
| Status enum + tahapan stepper | `EnumStatusRealisasi`, `EnumTahapanRealisasi` |
| Pembagian tab antrean per tahap | `HasVerificationStageScopes`, `HasVerificationStageTabs` |
| Filter tabel verifikasi | `HasVerificationTableFilters` |
| Aksi keputusan per tahap | `TahapVerifikasiRealisasiAction` + `Setujui/Revisi/Tolak` |
| Riwayat naratif | `RealisasiProgramKerjaLog` + `RealisasiProgramKerja::catatLog()` |
| Notifikasi database | `NotifikasiVerifikasi`, `AturanPenerimaNotifikasi` |
| Unggah berkas + pratinjau | `FileUpload` + `MediaAction` |
| Catatan revisi di modal perbaikan | `App\Filament\Forms\Components\CatatanRevisi` |
| Stepper di halaman detail | `resources/views/filament/infolists/realisasi-stepper.blade.php` |

**Penyederhanaan yang disengaja:** berkas bukti disimpan sebagai JSON path pada
`pemasukans` (`bukti_path` + `bukti_original_names`), **tanpa** tabel dokumen terpisah
seperti `realisasi_dokumens`. Pemasukan hanya punya satu jenis lampiran dan tidak
memerlukan riwayat versi berkas.

---

## Alur Status

```
Draft ──[Ajukan]──────────────> Diajukan
                                   │
Diajukan / VerifikasiRektor ───────┤ Setujui ──> VerifikasiWakil
                                   ├ Revisi  ──> Revisi
                                   └ Tolak   ──> Ditolak (final)

VerifikasiWakil ───────────────────┤ Setujui ──> VerifikasiKeuangan
                                   ├ Revisi  ──> Revisi
                                   └ Tolak   ──> Ditolak (final)

VerifikasiKeuangan ────────────────┤ Setujui ──> MenungguBukti
                                   ├ Revisi  ──> Revisi
                                   └ Tolak   ──> Ditolak (final)

MenungguBukti ──[Unggah Bukti Tanda Terima oleh unit]──> Valid ✅

Revisi ──[Ajukan ulang]──> kembali ke tahap yang meminta revisi
                           (via statusSebelumCabang(), bukan mengulang dari Rektor)
```

Hanya status **Valid** yang dihitung sebagai pemasukan sungguhan di Buku Anggaran.

---

## Urutan Implementasi

Tiap langkah berdiri sendiri dan bisa diverifikasi sebelum lanjut.

### Tahap 1 — Jenis waktu (independen, bisa dikerjakan lebih dulu)

1. `EnumJenisWaktuPemasukan` (`SatuHari`, `Rentang`).
2. Migration `add_jenis_waktu_to_pemasukans_table`: `jenis_waktu`, `tanggal_selesai`.
3. Model `Pemasukan`: fillable + cast + `labelPeriode()`.
4. `PemasukanForm`: Select `jenis_waktu` live + DatePicker `tanggal_selesai` kondisional.
5. Tabel, infolist, dan `PemasukansExport` menampilkan periode.
6. Test: rentang wajib mengisi tanggal selesai & tidak boleh mundur.

### Tahap 2 — Fondasi verifikasi (data & model)

7. `EnumStatusPemasukan`, `EnumTahapanPemasukan`.
8. Migration `add_verifikasi_to_pemasukans_table` — **termasuk backfill** seluruh
   pemasukan lama menjadi `valid` agar tidak hilang dari Buku Anggaran.
9. Migration `create_pemasukan_logs_table` + model `PemasukanLog`.
10. Model `Pemasukan`: relasi aktor/pengaju/log, `catatLog()`, `tahapanStepper()`,
    `statusTujuanPengajuan()`, `labelStatus()`, `dapatDiajukan()`.
11. `PemasukanFactory` (belum ada) + state per status untuk keperluan test.

### Tahap 3 — Aksi

12. `TahapVerifikasiPemasukanAction` (abstract, pemetaan tahap).
13. `SetujuiPemasukanAction`, `RevisiPemasukanAction`, `TolakPemasukanAction`.
14. `AjukanPemasukanAction` (unit kerja, sekaligus "Perbaiki" saat revisi).
15. **Setting dinamis** — konstanta + `DEFAULTS` + accessor pada `Setting`, lalu
    section baru pada `PengaturanSistem` (`mount()`, `form()`, `save()`).
    Dikerjakan sebelum langkah 16 karena aksi unggah membacanya.
16. `UnggahBuktiPemasukanAction` (unit kerja, `MenungguBukti` → `Valid`).

### Tahap 4 — Resource verifikasi

17. Tambah `'Verifikasi Pemasukan'` pada `AppPanelProvider::navigationGroups()`
    (setelah `'Pemasukan'`, sebelum `'Verifikasi Pengajuan'`).
18. `VerifikasiRektorPemasukans/`, `VerifikasiWakilPemasukans/`,
    `VerifikasiKeuanganPemasukans/` — masing-masing Resource + Tables + Pages(List, View).
19. `PemasukanInfolist` dijadikan reusable (`configure(Schema $schema, ?string $resource = null)`)
    + stepper blade `pemasukan-stepper.blade.php`.

### Tahap 5 — Integrasi & dampak lintas modul

20. `NotifikasiVerifikasi` + `AturanPenerimaNotifikasi`: peristiwa pemasukan.
21. `BukuAnggaran::barisPemasukan()` disaring ke status Valid.
22. `PemasukanOverview` widget: pisahkan "Total Valid" vs "Menunggu Verifikasi".
23. `PemasukanResource::canEdit()/canDelete()` dikunci ke Draft/Revisi.
24. `PemasukansExport` menambah kolom status.
25. `ProgramKerjaSeeder`: pemasukan contoh diberi status `Valid` + `user_id`.

### Tahap 6 — Test

26. `tests/Feature/PemasukanVerifikasiTest.php` + kasus setting dinamis pada
    `tests/Feature/PengaturanSistemTest.php`.

---

## Dampak Lintas Modul (jangan terlewat)

| Berkas | Perubahan | Alasan |
| ------ | --------- | ------ |
| `app/Services/BukuAnggaran.php` → `barisPemasukan()` | `->where('status', EnumStatusPemasukan::Valid)` | Keputusan #3 |
| `app/Filament/Resources/Pemasukans/Widgets/PemasukanOverview.php` | Stat dipisah Valid vs menunggu | Angka ringkasan harus konsisten dengan Buku Anggaran |
| `app/Exports/PemasukansExport.php` | Kolom `jenis_waktu`, `tanggal_selesai`, `status` | Ekspor harus mencerminkan data baru |
| `app/Providers/Filament/AppPanelProvider.php` | Grup navigasi baru | Keputusan #1 |
| `database/seeders/ProgramKerjaSeeder.php` | Pemasukan contoh berstatus Valid | Agar seeder tetap menghasilkan Buku Anggaran yang berisi |
| `database/migrations/…_add_verifikasi_to_pemasukans_table.php` | Backfill `status = 'valid'` | Data lama tidak boleh hilang dari Buku Anggaran |
| `tests/Feature/BukuAnggaranTest.php`, `PemasukanTest.php` | Pemasukan uji perlu status Valid | Kalau tidak, saldo pada test lama berubah |

> **Catatan `BukuAnggaran`:** pemasukan tanpa tautan pengajuan/realisasi disaring
> memakai `whereBetween('tanggal_pelaksanaan', …)`. Untuk pemasukan rentang, yang
> dipakai tetap tanggal **mulai** — konsisten dengan cara mutasi lain dicatat pada
> satu titik waktu.

---

## Acceptance Criteria (tingkat fitur)

- Form pemasukan menawarkan jenis waktu 1 hari / rentang; rentang wajib mengisi
  tanggal selesai yang tidak boleh mendahului tanggal mulai.
- Tabel dan detail menampilkan periode sebagai satu teks (`12 Agustus 2026` atau
  `12 – 15 Agustus 2026`).
- Pemasukan baru berstatus Draf dan hanya bisa diubah/dihapus selagi Draf atau Revisi.
- Alur Rektor → Wakil Rektor → Biro Keuangan berjalan berurutan, masing-masing dengan
  tab Perlu Diverifikasi / Sudah Direspon / Ditolak.
- Revisi mengembalikan pemasukan ke unit kerja dan, setelah diperbaiki, kembali ke
  tahap yang meminta revisi — bukan mengulang dari Rektor.
- Penolakan bersifat final.
- Setelah Biro Keuangan menyetujui, unit kerja mengunggah bukti tanda terima dan
  pemasukan menjadi Valid.
- Hanya pemasukan Valid yang muncul di Buku Anggaran dan terhitung pada widget.
- Seluruh peristiwa tercatat pada riwayat dan memicu notifikasi ke pihak yang tepat.

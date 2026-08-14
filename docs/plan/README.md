# Rencana Implementasi — Sistem Manajemen Kinerja (UMLA)

Dokumen induk perencanaan. Sumber kebutuhan: [`../konsep.md`](../konsep.md).
Setiap resource punya file spec di `lists/` (format skill `filament-resource-spec`).
Implementasi mengikuti skill: `filament-project-conventions`, `filament-resource-actions`,
`filament-role-permission`, `filament-user-management`, `filament-excel-import-export`.

## Keputusan Arsitektur (dikonfirmasi user)

1. **User** — model User dasar bergaya LAMADU: login memakai `username` (bukan email),
   password auto-generate, **tanpa relasi persona** (dosen/mahasiswa/tendik). Skill
   `filament-user-management` mode **DASAR** + username **manual**.
2. **Export/Import** (openspout, skill `filament-excel-import-export`) dipasang di:
   semua Master Data, Acuan & Penawaran Program Kerja, Pengguna, serta **export** untuk
   data transaksi (Pengajuan & Realisasi).
3. **Verifikasi** — pola **Resource per tahap + status enum**. Satu model transaksi
   (`Pengajuan`/`Realisasi`) menyimpan status alur; tiap menu verifikasi adalah Resource
   yang memfilter data pada tahapnya dan menyediakan action Setujui/Tolak/Revisi.
4. **Seeder** — seeder + factory lengkap untuk seluruh master data dan contoh alur
   program kerja end-to-end.

## Fondasi yang sudah ada

- Panel Filament v5 (`AppPanelProvider`), login aktif, primary color Amber.
- Sistem Role & Permission (Spatie) + Authorized*/Captcha* actions + `RoleResource`.
- `EnumPermission`, `EnumRole`, `PermissionRegistrar`, `HasResourceAuthorization`.
- DB MySQL di Docker (`shared-mysql`, database `kinerja`). Artisan dijalankan via
  `docker exec -w /var/www/kinerja kinerja-app-php php artisan …` (Makefile menyediakan
  `make artisan cmd="…"`, `make fresh`, `make migrate`).

## Navigation Groups (urutan sidebar)

```
Master Data          → Periode, Tahun Kerja, Kategori, Bidang, Unit Kerja, Program
Anggaran             → Rekening, Pagu Anggaran
Program Kerja        → Acuan Program Kerja, Penawaran Program Kerja
Pelaksanaan          → Daftar Program Kerja, Pengajuan Program Kerja, Realisasi Program Kerja
Verifikasi Pengajuan → Verifikasi Pengajuan (Tahap 1)
Verifikasi Realisasi → Verifikasi Rektor, Verifikasi Wakil Rektor, Verifikasi Biro Keuangan, Verifikasi Laporan
Manajemen Akses      → Pengguna, Role (sudah ada)
Pengaturan Sistem    → (cadangan)
```

`AppPanelProvider::navigationGroups([...])` harus diperbarui menambahkan group baru.

## Peta Entitas (ERD ringkas)

```
Periode 1──* TahunKerja
TahunKerja 1──* PaguAnggaran ──* UnitKerja
Rekening 1──* (kode_akun) Acuan/Penawaran

AcuanProgramKerja *──1 UnitKerja, Bidang, Kategori, Program, Rekening
AcuanProgramKerja 1──* AcuanTarget ──1 TahunKerja      (target per tahun kerja)

PenawaranProgramKerja *──1 AcuanProgramKerja (sumber), UnitKerja, Bidang,
                                Kategori, Program, Rekening, TahunKerja

PengajuanProgramKerja *──1 PenawaranProgramKerja, UnitKerja, User(pengaju)
RealisasiProgramKerja *──1 PengajuanProgramKerja
```

Aturan kunci Tahun Kerja Aktif: hanya **satu** `TahunKerja` boleh `is_active=true`
(begitu pula Periode). Pagu, Penawaran, Daftar Program Kerja, dan Pengajuan selalu
mengacu ke Tahun Kerja aktif.

## Enums (backed enum, `app/Enums/`, implement `HasLabel`)

### `EnumStatusPengajuan` — status Pengajuan Program Kerja
| key       | value      | label     | keterangan |
| --------- | ---------- | --------- | ---------- |
| Draft     | 'draft'    | Draf      | belum diajukan, unit masih edit |
| Diajukan  | 'diajukan' | Diajukan  | menunggu Verifikasi Tahap 1 |
| Revisi    | 'revisi'   | Revisi    | dikembalikan, unit boleh edit lalu ajukan ulang |
| Ditolak   | 'ditolak'  | Ditolak   | final ditolak |
| Diterima  | 'diterima' | Diterima  | lolos → boleh membuat Realisasi |

### `EnumStatusRealisasi` — status alur Realisasi (menggerakkan menu verifikasi)
| key                 | value                  | label                    | tahap owner |
| ------------------- | ---------------------- | ------------------------ | ----------- |
| Draft               | 'draft'                | Draf                     | Unit |
| Diajukan            | 'diajukan'             | Diajukan                 | → Rektor |
| VerifikasiRektor    | 'verifikasi_rektor'    | Verifikasi Rektor        | Rektor |
| VerifikasiWakil     | 'verifikasi_wakil'     | Verifikasi Wakil Rektor  | Wakil Rektor |
| VerifikasiKeuangan  | 'verifikasi_keuangan'  | Verifikasi Biro Keuangan | Biro Keuangan |
| Dijadwalkan         | 'dijadwalkan'          | Dijadwalkan              | Biro Keuangan |
| MenungguLaporan     | 'menunggu_laporan'     | Menunggu Laporan         | Unit (upload laporan) |
| VerifikasiLaporan   | 'verifikasi_laporan'   | Verifikasi Laporan       | Verifikator Laporan |
| Selesai             | 'selesai'              | Selesai                  | — |
| Ditolak             | 'ditolak'              | Ditolak                  | — |
| Revisi              | 'revisi'               | Revisi                   | Unit |

Transisi status (happy path):
```
Draft → Diajukan → VerifikasiRektor → VerifikasiWakil → VerifikasiKeuangan
      → (Dijadwalkan | MenungguLaporan) → [unit upload laporan] → VerifikasiLaporan → Selesai
```
Cabang: setiap tahap verifikasi bisa `Ditolak` (final) atau `Revisi` (kembali ke Unit → `Draft/Revisi`).

### `EnumHasilVerifikasi` — pilihan aksi verifikator (shared)
| key      | value      | label     |
| -------- | ---------- | --------- |
| Setuju   | 'setuju'   | Disetujui |
| Revisi   | 'revisi'   | Revisi    |
| Tolak    | 'tolak'    | Ditolak   |

### `EnumStatusPencairan` — sub-status di Biro Keuangan
| key             | value              | label            |
| --------------- | ------------------ | ---------------- |
| Dijadwalkan     | 'dijadwalkan'      | Dijadwalkan      |
| MenungguLaporan | 'menunggu_laporan' | Menunggu Laporan |
| Dicairkan       | 'dicairkan'        | Sudah Dicairkan  |

## Model transaksi — kolom verifikasi

`RealisasiProgramKerja` menyimpan jejak tiap tahap (nullable):
- `status` (EnumStatusRealisasi), `catatan_verifikasi` (log/last note)
- `nominal_disetujui_rektor`, `disetujui_rektor_at`, `rektor_id`
- `nominal_disetujui_wakil`, `disetujui_wakil_at`, `wakil_id`
- `status_pencairan` (EnumStatusPencairan), `jadwal_pencairan` (date),
  `dicairkan_at`, `keuangan_id`
- `laporan_path` (file), `laporan_diserahkan_at`
- `laporan_disetujui_at`, `verifikator_laporan_id`

> Riwayat verifikasi detail (opsional, jika perlu audit): tabel `verifikasi_logs`
> polimorfik — **ditunda** ke iterasi lanjutan; MVP memakai kolom di atas.

**Tab per tahap verifikasi.** Tiap menu verifikasi punya tab: *Perlu Diverifikasi*
(default), *Sudah Direspon* (setuju/revisi), dan *Ditolak* (untuk tahap yang bisa
menolak: Pengajuan, Rektor, Wakil). Karena status bergerak maju, atribusi outcome ke
tahap memakai kolom aktor (`rektor_id`/`wakil_id`/`keuangan_id`/`verifikator_laporan_id`):
tahap "menangani" record bila aktornya terisi, dan penolakan diatribusikan ke tahap ini
hanya bila aktor tahap berikutnya masih kosong (alur sekuensial). Logika di
`HasVerificationStageScopes`; render tab di `HasVerificationStageTabs`. Aksi verifikasi
hanya tampil pada record yang masih pending di tahapnya. Keuangan & Laporan tak punya
tab Ditolak (tak bisa menolak).

**Jadwal pencairan = model terpisah (`JadwalPencairan`)** — sudah diimplementasi.
Biro Keuangan (aksi "Proses Pencairan") membuat record `JadwalPencairan`
(realisasi, tanggal, nominal, status `EnumStatusPencairan`, keuangan_id). Resource
**"Jadwal Pencairan"** (grup Verifikasi Realisasi) menjadi buku jadwal terurut tanggal
dengan aksi "Tandai Dicairkan" → menandai jadwal `Dicairkan` dan memindahkan realisasi
ke `MenungguLaporan`. Kolom `jadwal_pencairan`/`status_pencairan`/`dicairkan_at` di
Realisasi tetap disinkronkan sebagai denormalisasi (dipakai export & infolist).

## Kelompok Acuan Program Kerja (5 tahunan)

Acuan Program Kerja dikelompokkan per rencana jangka menengah lewat model
**`KelompokAcuan`** (`name`, `tahun_mulai`, `tahun_selesai`, `is_active` single-active
seperti TahunKerja). `AcuanProgramKerja.kelompok_acuan_id` (nullOnDelete). Resource
**"Kelompok Acuan"** (grup Program Kerja) berisi RelationManager daftar acuan miliknya.
Menu **Acuan Program Kerja** memfilter default ke kelompok aktif (`SelectFilter` +
default `KelompokAcuan::active()`), tetap bisa diganti ke kelompok lain.

## Pemasukan Unit

Model **`Pemasukan`** mencatat pendapatan tiap unit: `unit_kerja_id`, `sumber`
(`EnumSumberPemasukan`: Pengajuan|Realisasi), salah satu `pengajuan_program_kerja_id`
atau `realisasi_program_kerja_id` (nullOnDelete), `rincian_kegiatan`,
`tanggal_pelaksanaan`, `nominal_pendapatan`, `keterangan`. Resource **"Pemasukan Unit"**
(grup **Pemasukan** baru) — form memilih jenis sumber lalu record program kerja terkait,
tabel dengan total pendapatan (summarizer) dan data-scope per unit. Export saja.

## Kolom "kode akun"

Konsep menyebut `kode akun` sebagai kolom di Acuan/Penawaran dan `Rekening` sebagai
master. Diputuskan: `kode akun` = relasi `belongsTo(Rekening)` (`rekening_id`),
ditampilkan sebagai Select searchable. Alasan: menjaga integritas & reuse master
Rekening. Dicatat sebagai penyimpangan dari pembacaan literal konsep.

## Urutan Pengerjaan (dependency order)

| # | Stage | Item | Depends on |
|---|-------|------|-----------|
| 0 | Fondasi | Package sluggable, infra export/import, group nav, User dasar (username) | — |
| 1 | Master Data | Periode → Tahun Kerja → Kategori → Bidang → Unit Kerja → Program | 0 |
| 2 | Anggaran | Rekening → Pagu Anggaran | 1 |
| 3 | Program Kerja | Acuan Program Kerja (+AcuanTarget) → Penawaran Program Kerja | 1,2 |
| 4 | Pelaksanaan | Daftar Program Kerja → Pengajuan → Realisasi | 3 |
| 5 | Verifikasi Pengajuan | Verifikasi Pengajuan (Tahap 1) | 4 |
| 6 | Verifikasi Realisasi | Rektor → Wakil Rektor → Biro Keuangan (+tab Jadwal) → Laporan | 4,5 |
| 7 | Pengguna | Manajemen Pengguna (dasar, username) | 0 |
| 8 | Export/Import | Pasang aksi Export/Import di resource sesuai keputusan #2 | 1–4,7 |
| 9 | Seeder & Test | Factory + Seeder lengkap; feature test alur inti | semua |

## Rencana Export/Import

Infra dari skill `filament-excel-import-export` (openspout — cek versi PHP dulu):
abstract `Export`/`Import`, service openspout, aksi Filament, command generator, stub.

Target awal:
- Master Data: Periode, Tahun Kerja, Kategori, Bidang, Unit Kerja, Program, Rekening, Pagu Anggaran — Export **dan** Import + template.
- Program Kerja: Acuan, Penawaran — Export **dan** Import (import massal antar tahun).
- Pengguna — Export **dan** Import (dengan kolom role, password auto-generate saat import).
- Transaksi: Pengajuan, Realisasi — **Export** saja (rekap laporan).

## Permission & Role

Permission per Resource/Page auto-scan (skill `filament-role-permission`). Role fungsional
yang dibutuhkan alur (dibuat via seeder, ditambahkan ke `EnumRole` bila perlu):
`Super Admin` (bypass), `Admin`, `Unit Kerja`, `Rektor`, `Wakil Rektor`,
`Biro Keuangan`, `Verifikator Laporan`.

Data scope per Unit Kerja **sudah diimplementasi** (permission-based, LAMADU Ref 08):
`PermissionRegistrar::dataScopeEntities()` mendaftarkan entitas `unit`, sehingga form
Role punya section "Pembatasan Data → Akses Data per Unit Kerja". Resource Pengajuan,
Realisasi, dan Daftar Program Kerja memakai `PermissionRegistrar::permittedUnitIds($user)`
= unit sendiri (`users.unit_kerja_id`) ∪ unit dari permission `view_data_unit_{id}`.
User non-privileged tanpa unit & tanpa scope → tidak melihat data (celah ditutup);
verifikator (Rektor/Wakil/Keuangan/Laporan) tetap melihat semua sesuai tahapannya.

## Testing

Feature test (Pest + `pestphp/pest-plugin-livewire`) minimal per resource: create,
validasi, list. Alur inti diuji end-to-end: buat Penawaran → Pengajuan → verif Tahap 1
→ Realisasi → Rektor → Wakil → Keuangan → Laporan → Selesai. Jalankan
`php artisan test --compact` per file setelah perubahan.

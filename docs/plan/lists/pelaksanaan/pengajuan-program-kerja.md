## Resource: `PengajuanProgramKerja` (Pengajuan Program Kerja)

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\PengajuanProgramKerja`     |
| Navigation Group | `Pelaksanaan`                          |
| Navigation Label | `Pengajuan Program Kerja`              |
| Plural Label     | `Data Pengajuan Program Kerja`         |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Unit Kerja hanya melihat pengajuannya  |
| Shared Component | Tidak (resource-specific)              |

---

### Enums

#### gunakan `EnumStatusPengajuan.php`
Status alur verifikasi tahap 1 (Draf/Diajukan/Revisi/Ditolak/Diterima). Lihat `docs/plan/README.md`.

---

### Migration

| Kolom                        | Tipe                                                                              | Keterangan                    |
| ---------------------------- | --------------------------------------------------------------------------------- | ----------------------------- |
| `id`                         | `bigIncrements`                                                                   | Primary key                   |
| `penawaran_program_kerja_id` | `foreignId`->`constrained('penawaran_program_kerjas')`->`cascadeOnDelete()`       | Penawaran yang diajukan       |
| `unit_kerja_id`              | `foreignId`->`constrained('unit_kerjas')`->`cascadeOnDelete()`                    | Unit pengaju                  |
| `user_id`                    | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                   | Pengaju                       |
| `alokasi_anggaran`           | `decimal(18,2)`, default `0`                                                      | Pengajuan/alokasi anggaran    |
| `deskripsi_kegiatan`         | `text`, nullable                                                                | Deskripsi kegiatan            |
| `status`                     | `string`, default `'draft'`                                                      | `EnumStatusPengajuan`         |
| `catatan_verifikasi`         | `text`, nullable                                                                | Catatan verifikator tahap 1   |
| `diverifikasi_at`            | `dateTime`, nullable                                                            | Waktu verifikasi              |
| `verifikator_id`             | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                  | User verifikator tahap 1      |
| `timestamps`                 | —                                                                               | `created_at`, `updated_at`    |

---

### Model

- Fillable: `penawaran_program_kerja_id`, `unit_kerja_id`, `user_id`, `alokasi_anggaran`, `deskripsi_kegiatan`, `status`, `catatan_verifikasi`, `diverifikasi_at`, `verifikator_id`
- Cast: `alokasi_anggaran` → `decimal:2`, `status` → `EnumStatusPengajuan::class`, `diverifikasi_at` → `datetime`
- RouteKeyName: default `id`
- Relationships:
    - `penawaranProgramKerja()` → `belongsTo(PenawaranProgramKerja::class)`
    - `unitKerja()` → `belongsTo(UnitKerja::class)`
    - `user()` → `belongsTo(User::class)`
    - `verifikator()` → `belongsTo(User::class, 'verifikator_id')`
    - `realisasi()` → `hasOne(RealisasiProgramKerja::class)`
- Boot: saat create, isi `user_id` & `unit_kerja_id` dari user login bila kosong.

---

### Form Fields

| Field                        | Komponen    | Keterangan                                                            |
| ---------------------------- | ----------- | -------------------------------------------------------------------- |
| `penawaran_program_kerja_id`| `Select`    | Required, relationship `penawaranProgramKerja` (`name`), searchable, disabled saat edit |
| `alokasi_anggaran`          | `TextInput` | Required, numeric, prefix `Rp`, label `Pengajuan Anggaran`           |
| `deskripsi_kegiatan`        | `Textarea`  | Nullable, label `Deskripsi Kegiatan`, `->columnSpanFull()`           |

> `status`, `unit_kerja_id`, `user_id` tidak diedit manual (diisi sistem). Field hanya
> bisa diedit saat status `Draft` atau `Revisi`.

---

### Table Columns

| Kolom                          | Tipe         | Keterangan                              |
| ------------------------------ | ------------ | --------------------------------------- |
| `penawaranProgramKerja.name`  | `TextColumn` | Label `Program Kerja`, searchable, wrap |
| `unitKerja.name`              | `TextColumn` | Label `Unit Kerja`, toggleable          |
| `alokasi_anggaran`            | `TextColumn` | money `IDR`, label `Anggaran`, sortable |
| `status`                      | `TextColumn` | badge, label `Status` (warna per enum)  |
| `created_at`                  | `TextColumn` | Format tanggal, sortable, toggleable    |

---

### Table Filters

| Filter    | Tipe           | Keterangan                       |
| --------- | -------------- | -------------------------------- |
| `status`  | `SelectFilter` | Options `EnumStatusPengajuan`    |

---

### Actions (record)

- **Ajukan** — visible saat status `Draft`/`Revisi`; ubah status → `Diajukan`.
- Edit — hanya saat status `Draft`/`Revisi`.
- View, Delete (CAPTCHA) standar.

---

### Infolist Entries

| Entry                          | Tipe        | Keterangan                       |
| ------------------------------ | ----------- | -------------------------------- |
| `penawaranProgramKerja.name`  | `TextEntry` | Label "Program Kerja"            |
| `unitKerja.name`              | `TextEntry` | Label "Unit Kerja"              |
| `user.name`                   | `TextEntry` | Label "Pengaju"                  |
| `alokasi_anggaran`            | `TextEntry` | money "IDR", label "Pengajuan Anggaran" |
| `deskripsi_kegiatan`          | `TextEntry` | Label "Deskripsi Kegiatan"       |
| `status`                      | `TextEntry` | badge, label "Status"            |
| `catatan_verifikasi`          | `TextEntry` | Label "Catatan Verifikasi"       |
| `verifikator.name`            | `TextEntry` | Label "Diverifikasi Oleh"        |
| `diverifikasi_at`             | `TextEntry` | Label "Waktu Verifikasi", dateTime |
| `created_at`                  | `TextEntry` | Label "Dibuat", dateTime         |
| `updated_at`                  | `TextEntry` | Label "Diperbarui", dateTime     |

---

### Halaman (Pages)

#### ListPengajuanProgramKerjas
- Title: `Data Pengajuan Program Kerja`
- Subheading: `Berikut adalah daftar Pengajuan Program Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Pengajuan Program Kerja`

#### CreatePengajuanProgramKerja
- Title: `Buat Pengajuan Program Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Pengajuan Program Kerja berhasil dibuat`

#### EditPengajuanProgramKerja
- Notifikasi simpan: `Data Pengajuan Program Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewPengajuanProgramKerja
- Title: `Detail Pengajuan Program Kerja`
- Breadcrumb: `Data Pengajuan Program Kerja > Detail`

---

### Catatan Implementasi

- `getEloquentQuery()`: user non-privileged hanya melihat pengajuan `unit_kerja_id`-nya.
- Realisasi hanya boleh dibuat bila status `Diterima`.

### Acceptance Criteria

- Unit hanya bisa mengedit pengajuan saat status `Draft` atau `Revisi`.
- Menekan "Ajukan" mengubah status ke `Diajukan` dan mengunci form.
- Pengajuan `Diterima` memunculkan opsi membuat Realisasi.

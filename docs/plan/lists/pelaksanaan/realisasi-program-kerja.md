## Resource: `RealisasiProgramKerja` (Realisasi Program Kerja)

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\RealisasiProgramKerja`     |
| Navigation Group | `Pelaksanaan`                          |
| Navigation Label | `Realisasi Program Kerja`              |
| Plural Label     | `Data Realisasi Program Kerja`         |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Unit Kerja hanya melihat realisasinya  |
| Shared Component | Tidak (resource-specific)              |

---

### Enums

#### gunakan `EnumStatusRealisasi.php` + `EnumStatusPencairan.php`
Alur realisasi & sub-status pencairan. Lihat `docs/plan/README.md`.

---

### Migration

| Kolom                        | Tipe                                                                              | Keterangan                          |
| ---------------------------- | --------------------------------------------------------------------------------- | ----------------------------------- |
| `id`                         | `bigIncrements`                                                                   | Primary key                         |
| `pengajuan_program_kerja_id` | `foreignId`->`constrained('pengajuan_program_kerjas')`->`cascadeOnDelete()`       | Pengajuan sumber                    |
| `name`                       | `string`                                                                         | Nama realisasi/kegiatan             |
| `description`                | `text`, nullable                                                               | Deskripsi                           |
| `start_datetime`             | `dateTime`, nullable                                                            | Mulai kegiatan                      |
| `end_datetime`               | `dateTime`, nullable                                                            | Selesai kegiatan                    |
| `anggaran_digunakan`         | `decimal(18,2)`, default `0`                                                    | ≤ alokasi anggaran pengajuan        |
| `status`                     | `string`, default `'draft'`                                                    | `EnumStatusRealisasi`               |
| `catatan_verifikasi`         | `text`, nullable                                                               | Catatan tahap terakhir              |
| `nominal_disetujui_rektor`   | `decimal(18,2)`, nullable                                                       | Nominal disetujui rektor (opsional) |
| `disetujui_rektor_at`        | `dateTime`, nullable                                                           | Waktu ACC rektor                    |
| `rektor_id`                  | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                 | User rektor                         |
| `nominal_disetujui_wakil`    | `decimal(18,2)`, nullable                                                       | Nominal disetujui wakil rektor      |
| `disetujui_wakil_at`         | `dateTime`, nullable                                                           | Waktu ACC wakil rektor              |
| `wakil_id`                   | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                 | User wakil rektor                   |
| `status_pencairan`           | `string`, nullable                                                            | `EnumStatusPencairan`               |
| `jadwal_pencairan`           | `date`, nullable                                                              | Jadwal pencairan anggaran           |
| `dicairkan_at`               | `dateTime`, nullable                                                          | Waktu uang diterima                 |
| `keuangan_id`                | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                 | User biro keuangan                  |
| `laporan_path`               | `string`, nullable                                                            | File laporan (private)              |
| `laporan_diserahkan_at`      | `dateTime`, nullable                                                          | Waktu upload laporan                |
| `laporan_disetujui_at`       | `dateTime`, nullable                                                          | Waktu ACC laporan                   |
| `verifikator_laporan_id`     | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable                 | User verifikator laporan            |
| `timestamps`                 | —                                                                             | `created_at`, `updated_at`          |

---

### Model

- Fillable: `pengajuan_program_kerja_id`, `name`, `description`, `start_datetime`, `end_datetime`, `anggaran_digunakan`, `status`, `catatan_verifikasi`, `nominal_disetujui_rektor`, `disetujui_rektor_at`, `rektor_id`, `nominal_disetujui_wakil`, `disetujui_wakil_at`, `wakil_id`, `status_pencairan`, `jadwal_pencairan`, `dicairkan_at`, `keuangan_id`, `laporan_path`, `laporan_diserahkan_at`, `laporan_disetujui_at`, `verifikator_laporan_id`
- Cast: `status` → `EnumStatusRealisasi::class`, `status_pencairan` → `EnumStatusPencairan::class`, semua `*_at`/`*datetime` → `datetime`, `jadwal_pencairan` → `date`, semua nominal → `decimal:2`
- RouteKeyName: default `id`
- Relationships:
    - `pengajuanProgramKerja()` → `belongsTo(PengajuanProgramKerja::class)`
    - `rektor()`, `wakil()`, `keuangan()`, `verifikatorLaporan()` → `belongsTo(User::class, '<kolom>_id')`
- Accessor `alokasiAnggaran` → `pengajuanProgramKerja->alokasi_anggaran` (batas atas).

---

### Form Fields (unit kerja — buat/edit realisasi)

Field verifikasi (nominal/jadwal/status) TIDAK di form ini; diisi lewat action di
resource verifikasi. Form realisasi milik unit hanya berisi:

| Field                        | Komponen         | Keterangan                                                            |
| ---------------------------- | ---------------- | -------------------------------------------------------------------- |
| `pengajuan_program_kerja_id`| `Select`         | Required, relationship (only status `Diterima`), searchable, disabled saat edit |
| `name`                      | `TextInput`      | Required, label `Nama Kegiatan`, `->columnSpanFull()`                |
| `description`               | `Textarea`       | Nullable, label `Deskripsi`, `->columnSpanFull()`                    |
| `start_datetime`            | `DateTimePicker` | Nullable, label `Mulai`                                              |
| `end_datetime`              | `DateTimePicker` | Nullable, label `Selesai`, after `start_datetime`                    |
| `anggaran_digunakan`        | `TextInput`      | Required, numeric, prefix `Rp`, label `Anggaran Digunakan`, ≤ alokasi |

Field laporan (`laporan_path` FileUpload) muncul saat status `MenungguLaporan` (unit
upload laporan) — via action **Unggah Laporan**. Jangan set visibility public.

---

### Table Columns

| Kolom                            | Tipe         | Keterangan                                 |
| -------------------------------- | ------------ | ------------------------------------------ |
| `name`                          | `TextColumn` | Searchable, sortable, wrap                 |
| `pengajuanProgramKerja.unitKerja.name` | `TextColumn` | Label `Unit Kerja`, toggleable       |
| `anggaran_digunakan`            | `TextColumn` | money `IDR`, sortable                      |
| `status`                        | `TextColumn` | badge, label `Status` (warna per enum)     |
| `created_at`                    | `TextColumn` | Format tanggal, sortable, toggleable       |

---

### Table Filters

| Filter    | Tipe           | Keterangan                       |
| --------- | -------------- | -------------------------------- |
| `status`  | `SelectFilter` | Options `EnumStatusRealisasi`    |

---

### Actions (record, milik unit)

- **Ajukan Realisasi** — visible status `Draft`/`Revisi` → status `Diajukan` (masuk antrean Rektor).
- **Unggah Laporan** — visible status `MenungguLaporan`; schema `FileUpload laporan_path` →
  set `laporan_diserahkan_at`, status → `VerifikasiLaporan`.
- Edit hanya saat status `Draft`/`Revisi`.

---

### Infolist Entries

| Entry                        | Tipe        | Keterangan                          |
| ---------------------------- | ----------- | ----------------------------------- |
| `name`                      | `TextEntry` | Label "Nama Kegiatan"               |
| `pengajuanProgramKerja.penawaranProgramKerja.name` | `TextEntry` | Label "Program Kerja" |
| `description`               | `TextEntry` | Label "Deskripsi"                   |
| `start_datetime`            | `TextEntry` | Label "Mulai", dateTime             |
| `end_datetime`              | `TextEntry` | Label "Selesai", dateTime           |
| `anggaran_digunakan`        | `TextEntry` | money "IDR", label "Anggaran Digunakan" |
| `status`                    | `TextEntry` | badge, label "Status"               |
| `nominal_disetujui_rektor`  | `TextEntry` | money "IDR", label "Disetujui Rektor" |
| `nominal_disetujui_wakil`   | `TextEntry` | money "IDR", label "Disetujui Wakil Rektor" |
| `status_pencairan`          | `TextEntry` | badge, label "Status Pencairan"     |
| `jadwal_pencairan`          | `TextEntry` | Label "Jadwal Pencairan", date      |
| `dicairkan_at`              | `TextEntry` | Label "Dicairkan", dateTime         |
| `catatan_verifikasi`        | `TextEntry` | Label "Catatan Verifikasi"          |
| `created_at`                | `TextEntry` | Label "Dibuat", dateTime            |
| `updated_at`                | `TextEntry` | Label "Diperbarui", dateTime        |

---

### Halaman (Pages)

#### ListRealisasiProgramKerjas
- Title: `Data Realisasi Program Kerja`
- Subheading: `Berikut adalah daftar Realisasi Program Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Realisasi Program Kerja`

#### CreateRealisasiProgramKerja
- Title: `Buat Realisasi Program Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Realisasi Program Kerja berhasil dibuat`

#### EditRealisasiProgramKerja
- Notifikasi simpan: `Data Realisasi Program Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewRealisasiProgramKerja
- Title: `Detail Realisasi Program Kerja`
- Breadcrumb: `Data Realisasi Program Kerja > Detail`

---

### Acceptance Criteria

- `anggaran_digunakan` tidak boleh melebihi `alokasi_anggaran` pengajuan induk.
- Realisasi hanya dibuat dari Pengajuan berstatus `Diterima`.
- Status berjalan sesuai transisi di README; tiap tahap verifikasi mengubah status.

## Resource: `TahunKerja` (Tahun Kerja)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\TahunKerja`   |
| Navigation Group | `Master Data`             |
| Navigation Label | `Tahun Kerja`             |
| Plural Label     | `Data Tahun Kerja`        |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom            | Tipe                                                        | Keterangan                   |
| ---------------- | ---------------------------------------------------------- | ---------------------------- |
| `id`             | `bigIncrements`                                            | Primary key                  |
| `periode_id`     | `foreignId`->`constrained('periodes')`->`cascadeOnDelete()`| Induk periode                |
| `name`           | `string`                                                   | Required                     |
| `slug`           | `string`, `unique`                                        | Auto-generate dari `name`    |
| `description`    | `text`, nullable                                          | Deskripsi                    |
| `start_datetime` | `dateTime`                                                | Awal tahun kerja             |
| `end_datetime`   | `dateTime`                                                | Akhir tahun kerja            |
| `is_active`      | `boolean`, default `false`                               | Tahun kerja aktif (hanya 1)  |
| `timestamps`     | —                                                         | `created_at`, `updated_at`   |

---

### Model

- Fillable: `periode_id`, `name`, `description`, `start_datetime`, `end_datetime`, `is_active`
- Cast: `is_active` → `boolean`, `start_datetime`/`end_datetime` → `datetime`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `periode()` → `belongsTo(Periode::class)`
    - `paguAnggarans()` → `hasMany(PaguAnggaran::class)`
    - `penawaranProgramKerjas()` → `hasMany(PenawaranProgramKerja::class)`
- Static helper `active(): ?self` mengembalikan tahun kerja aktif (cache request-scoped).
- Boot: saat `is_active=true`, nonaktifkan tahun kerja lain.

---

### Form Fields

| Field            | Komponen         | Keterangan                                              |
| ---------------- | ---------------- | ------------------------------------------------------ |
| `periode_id`    | `Select`         | Required, relationship `periode` (`name`), searchable  |
| `name`          | `TextInput`      | Required, label `Nama Tahun Kerja`, live on blur       |
| `description`   | `Textarea`       | Nullable, label `Deskripsi`, `->columnSpanFull()`      |
| `start_datetime`| `DateTimePicker` | Required, label `Mulai`                                |
| `end_datetime`  | `DateTimePicker` | Required, label `Selesai`, after `start_datetime`      |
| `is_active`     | `Toggle`         | Label `Status Aktif`, default `false`                  |

---

### Table Columns

| Kolom            | Tipe           | Keterangan               |
| ---------------- | -------------- | ------------------------ |
| `name`          | `TextColumn`   | Searchable, sortable     |
| `periode.name`  | `TextColumn`   | Label `Periode`, searchable |
| `start_datetime`| `TextColumn`   | Format tanggal, sortable |
| `end_datetime`  | `TextColumn`   | Format tanggal, sortable |
| `is_active`     | `ToggleColumn` | toggleable               |
| `created_at`    | `TextColumn`   | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter       | Tipe           | Keterangan                       |
| ------------ | -------------- | -------------------------------- |
| `periode_id` | `SelectFilter` | Relationship `periode`, label `Periode` |
| `is_active`  | `SelectFilter` | Filter status aktif/nonaktif     |

---

### Infolist Entries

| Entry            | Tipe        | Keterangan                    |
| ---------------- | ----------- | ----------------------------- |
| `periode.name`  | `TextEntry` | Label "Periode"               |
| `name`          | `TextEntry` | Label "Nama Tahun Kerja"      |
| `description`   | `TextEntry` | Label "Deskripsi"             |
| `start_datetime`| `TextEntry` | Label "Mulai", dateTime       |
| `end_datetime`  | `TextEntry` | Label "Selesai", dateTime     |
| `is_active`     | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`    | `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`    | `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListTahunKerjas
- Title: `Data Tahun Kerja`
- Subheading: `Berikut adalah daftar Tahun Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Tahun Kerja`

#### CreateTahunKerja
- Title: `Buat Tahun Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Tahun Kerja berhasil dibuat`

#### EditTahunKerja
- Notifikasi simpan: `Data Tahun Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewTahunKerja
- Title: `Detail {name}`
- Breadcrumb: `Data Tahun Kerja > Detail {name}`

---

### Acceptance Criteria

- Hanya satu Tahun Kerja `is_active=true` pada satu waktu.
- Modul Pagu, Penawaran, dan Pelaksanaan membaca Tahun Kerja aktif via `TahunKerja::active()`.

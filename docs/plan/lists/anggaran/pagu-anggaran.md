## Resource: `PaguAnggaran` (Pagu Anggaran)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\PaguAnggaran` |
| Navigation Group | `Anggaran`                |
| Navigation Label | `Pagu Anggaran`           |
| Plural Label     | `Data Pagu Anggaran`      |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom            | Tipe                                                                | Keterangan                        |
| ---------------- | ------------------------------------------------------------------- | --------------------------------- |
| `id`             | `bigIncrements`                                                     | Primary key                       |
| `tahun_kerja_id` | `foreignId`->`constrained('tahun_kerjas')`->`cascadeOnDelete()`     | Pagu untuk tahun kerja tertentu   |
| `unit_kerja_id`  | `foreignId`->`constrained('unit_kerjas')`->`cascadeOnDelete()`      | Pagu per unit kerja               |
| `amount`         | `decimal(18,2)`, default `0`                                        | Nominal pagu                      |
| `description`    | `text`, nullable                                                   | Keterangan                        |
| `timestamps`     | —                                                                 | `created_at`, `updated_at`        |

> Unique index gabungan (`tahun_kerja_id`, `unit_kerja_id`) — satu unit hanya punya
> satu pagu per tahun kerja. Tidak memakai `slug`/`is_active` (bukan entitas bernama).

---

### Model

- Fillable: `tahun_kerja_id`, `unit_kerja_id`, `amount`, `description`
- Cast: `amount` → `decimal:2`
- RouteKeyName: default `id` (tanpa slug)
- Relationships:
    - `tahunKerja()` → `belongsTo(TahunKerja::class)`
    - `unitKerja()` → `belongsTo(UnitKerja::class)`

---

### Form Fields

| Field            | Komponen    | Keterangan                                                                 |
| ---------------- | ----------- | ------------------------------------------------------------------------- |
| `tahun_kerja_id`| `Select`    | Required, relationship `tahunKerja` (`name`), default = tahun kerja aktif, searchable |
| `unit_kerja_id` | `Select`    | Required, relationship `unitKerja` (`name`), searchable                    |
| `amount`        | `TextInput` | Required, numeric, prefix `Rp`, label `Nominal Pagu`                       |
| `description`   | `Textarea`  | Nullable, label `Keterangan`, `->columnSpanFull()`                        |

---

### Table Columns

| Kolom             | Tipe         | Keterangan                          |
| ----------------- | ------------ | ----------------------------------- |
| `tahunKerja.name`| `TextColumn` | Label `Tahun Kerja`, searchable, sortable |
| `unitKerja.name` | `TextColumn` | Label `Unit Kerja`, searchable, sortable  |
| `amount`         | `TextColumn` | money `IDR`, sortable, label `Pagu` |
| `created_at`     | `TextColumn` | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter           | Tipe           | Keterangan                         |
| ---------------- | -------------- | ---------------------------------- |
| `tahun_kerja_id` | `SelectFilter` | Relationship `tahunKerja`, default tahun kerja aktif |
| `unit_kerja_id`  | `SelectFilter` | Relationship `unitKerja`           |

---

### Infolist Entries

| Entry             | Tipe        | Keterangan                    |
| ----------------- | ----------- | ----------------------------- |
| `tahunKerja.name`| `TextEntry` | Label "Tahun Kerja"           |
| `unitKerja.name` | `TextEntry` | Label "Unit Kerja"            |
| `amount`         | `TextEntry` | money "IDR", label "Nominal Pagu" |
| `description`    | `TextEntry` | Label "Keterangan"            |
| `created_at`     | `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`     | `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListPaguAnggarans
- Title: `Data Pagu Anggaran`
- Subheading: `Berikut adalah daftar Pagu Anggaran yang tersedia dalam sistem.`
- Header action: `Tambah Pagu Anggaran`

#### CreatePaguAnggaran
- Title: `Buat Pagu Anggaran Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Pagu Anggaran berhasil dibuat`

#### EditPaguAnggaran
- Notifikasi simpan: `Data Pagu Anggaran berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewPaguAnggaran
- Title: `Detail Pagu Anggaran`
- Breadcrumb: `Data Pagu Anggaran > Detail`

---

### Catatan Implementasi

- Default `tahun_kerja_id` di form & filter memakai `TahunKerja::active()?->id`.
- Konsep: "pengaturan pagu berdasarkan Tahun Kerja Aktif". Dimensi pagu diputuskan
  **per Unit Kerja** (paling berguna untuk kontrol alokasi pengajuan tiap unit).

### Acceptance Criteria

- Tidak boleh ada dua pagu untuk kombinasi Tahun Kerja + Unit Kerja yang sama.

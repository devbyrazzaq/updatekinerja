## Resource: `Periode` (Periode)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\Periode`      |
| Navigation Group | `Master Data`             |
| Navigation Label | `Periode`                 |
| Plural Label     | `Data Periode`            |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom             | Tipe                                                            | Keterangan                       |
| ----------------- | -------------------------------------------------------------- | -------------------------------- |
| `id`              | `bigIncrements`                                                | Primary key                      |
| `name`            | `string`                                                       | Required                         |
| `slug`            | `string`, `unique`                                             | Auto-generate dari `name`        |
| `description`     | `text`, nullable                                               | Deskripsi                        |
| `start_datetime`  | `dateTime`                                                     | Awal periode                     |
| `end_datetime`    | `dateTime`                                                     | Akhir periode                    |
| `user_id`         | `foreignId`->`constrained('users')`->`nullOnDelete()`, nullable | Pembuat periode                  |
| `is_active`       | `boolean`, default `false`                                     | Status aktif (hanya 1 aktif)     |
| `timestamps`      | —                                                              | `created_at`, `updated_at`       |

> Kolom konsep `status (boolean)` dipetakan ke baseline `is_active`. Kolom konsep
> `user_id` dipertahankan sebagai pemilik/pembuat.

---

### Model

- Fillable: `name`, `description`, `start_datetime`, `end_datetime`, `user_id`, `is_active`
- Cast: `is_active` → `boolean`, `start_datetime`/`end_datetime` → `datetime`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `user()` → `belongsTo(User::class)`
    - `tahunKerjas()` → `hasMany(TahunKerja::class)`
- `getSlugOptions()` generate dari `name`; `getRouteKeyName()` → `slug`.
- Boot/observer: saat `is_active=true` di-set, nonaktifkan periode lain (hanya 1 aktif).

---

### Form Fields

| Field            | Komponen           | Keterangan                                              |
| ---------------- | ------------------ | ------------------------------------------------------ |
| `name`          | `TextInput`        | Required, label `Nama Periode`, live on blur           |
| `description`   | `Textarea`         | Nullable, label `Deskripsi`, `->columnSpanFull()`      |
| `start_datetime`| `DateTimePicker`   | Required, label `Mulai`                                |
| `end_datetime`  | `DateTimePicker`   | Required, label `Selesai`, after `start_datetime`      |
| `user_id`       | `Select`           | Nullable, relationship `user` (`name`), default user login, searchable |
| `is_active`     | `Toggle`           | Label `Status Aktif`, default `false`                  |

---

### Table Columns

| Kolom            | Tipe           | Keterangan                       |
| ---------------- | -------------- | -------------------------------- |
| `name`          | `TextColumn`   | Searchable, sortable             |
| `start_datetime`| `TextColumn`   | Format tanggal, sortable         |
| `end_datetime`  | `TextColumn`   | Format tanggal, sortable         |
| `user.name`     | `TextColumn`   | Label `Dibuat Oleh`, toggleable  |
| `is_active`     | `ToggleColumn` | toggleable                       |
| `created_at`    | `TextColumn`   | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter      | Tipe           | Keterangan                   |
| ----------- | -------------- | ---------------------------- |
| `is_active` | `SelectFilter` | Filter status aktif/nonaktif |

---

### Infolist Entries

| Entry            | Tipe        | Keterangan                    |
| ---------------- | ----------- | ----------------------------- |
| `name`          | `TextEntry` | Label "Nama Periode"          |
| `description`   | `TextEntry` | Label "Deskripsi"             |
| `start_datetime`| `TextEntry` | Label "Mulai", dateTime       |
| `end_datetime`  | `TextEntry` | Label "Selesai", dateTime     |
| `user.name`     | `TextEntry` | Label "Dibuat Oleh"           |
| `is_active`     | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`    | `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`    | `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListPeriodes
- Title: `Data Periode`
- Subheading: `Berikut adalah daftar Periode yang tersedia dalam sistem.`
- Header action: `Tambah Periode`

#### CreatePeriode
- Title: `Buat Periode Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Periode berhasil dibuat`

#### EditPeriode
- Notifikasi simpan: `Data Periode berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewPeriode
- Title: `Detail {name}`
- Breadcrumb: `Data Periode > Detail {name}`

---

### Acceptance Criteria

- Mengaktifkan sebuah Periode otomatis menonaktifkan Periode lain (hanya satu aktif).
- `end_datetime` harus setelah `start_datetime`.

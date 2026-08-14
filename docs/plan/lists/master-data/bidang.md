## Resource: `Bidang` (Bidang)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\Bidang`       |
| Navigation Group | `Master Data`             |
| Navigation Label | `Bidang`                  |
| Plural Label     | `Data Bidang`             |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom         | Tipe                       | Keterangan                 |
| ------------- | -------------------------- | -------------------------- |
| `id`          | `bigIncrements`            | Primary key                |
| `code`        | `string`, `unique`         | Kode bidang                |
| `name`        | `string`                   | Required                   |
| `slug`        | `string`, `unique`         | Auto-generate dari `name`  |
| `description` | `text`, nullable           | Deskripsi                  |
| `is_active`   | `boolean`, default `true`  | Status aktif/nonaktif      |
| `timestamps`  | —                          | `created_at`, `updated_at` |

---

### Model

- Fillable: `code`, `name`, `description`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `acuanProgramKerjas()` → `hasMany(AcuanProgramKerja::class)`

---

### Form Fields

| Field         | Komponen    | Keterangan                                     |
| ------------- | ----------- | ---------------------------------------------- |
| `code`       | `TextInput` | Required, unique, label `Kode`                 |
| `name`       | `TextInput` | Required, label `Nama Bidang`, live on blur    |
| `description`| `Textarea`  | Nullable, label `Deskripsi`, `->columnSpanFull()` |
| `is_active`  | `Toggle`    | Label `Status Aktif`, default `true`           |

---

### Table Columns

| Kolom        | Tipe           | Keterangan               |
| ------------ | -------------- | ------------------------ |
| `code`      | `TextColumn`   | Searchable, sortable, badge |
| `name`      | `TextColumn`   | Searchable, sortable     |
| `is_active` | `ToggleColumn` | toggleable               |
| `created_at`| `TextColumn`   | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter      | Tipe           | Keterangan                   |
| ----------- | -------------- | ---------------------------- |
| `is_active` | `SelectFilter` | Filter status aktif/nonaktif |

---

### Infolist Entries

| Entry        | Tipe        | Keterangan                    |
| ------------ | ----------- | ----------------------------- |
| `code`      | `TextEntry` | Label "Kode"                  |
| `name`      | `TextEntry` | Label "Nama Bidang"           |
| `description`| `TextEntry` | Label "Deskripsi"             |
| `is_active` | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`| `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`| `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListBidangs
- Title: `Data Bidang`
- Subheading: `Berikut adalah daftar Bidang yang tersedia dalam sistem.`
- Header action: `Tambah Bidang`

#### CreateBidang
- Title: `Buat Bidang Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Bidang berhasil dibuat`

#### EditBidang
- Notifikasi simpan: `Data Bidang berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewBidang
- Title: `Detail {name}`
- Breadcrumb: `Data Bidang > Detail {name}`

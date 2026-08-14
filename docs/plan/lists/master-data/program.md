## Resource: `Program` (Program)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\Program`      |
| Navigation Group | `Master Data`             |
| Navigation Label | `Program`                 |
| Plural Label     | `Data Program`            |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom         | Tipe                       | Keterangan                 |
| ------------- | -------------------------- | -------------------------- |
| `id`          | `bigIncrements`            | Primary key                |
| `name`        | `string`                   | Required                   |
| `slug`        | `string`, `unique`         | Auto-generate dari `name`  |
| `description` | `text`, nullable           | Deskripsi                  |
| `is_active`   | `boolean`, default `true`  | Status aktif/nonaktif      |
| `timestamps`  | —                          | `created_at`, `updated_at` |

---

### Model

- Fillable: `name`, `description`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `acuanProgramKerjas()` → `hasMany(AcuanProgramKerja::class)`

---

### Form Fields

| Field         | Komponen    | Keterangan                                     |
| ------------- | ----------- | ---------------------------------------------- |
| `name`       | `TextInput` | Required, label `Nama Program`, live on blur   |
| `description`| `Textarea`  | Nullable, label `Deskripsi`, `->columnSpanFull()` |
| `is_active`  | `Toggle`    | Label `Status Aktif`, default `true`           |

---

### Table Columns

| Kolom        | Tipe           | Keterangan               |
| ------------ | -------------- | ------------------------ |
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
| `name`      | `TextEntry` | Label "Nama Program"          |
| `description`| `TextEntry` | Label "Deskripsi"             |
| `is_active` | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`| `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`| `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListPrograms
- Title: `Data Program`
- Subheading: `Berikut adalah daftar Program yang tersedia dalam sistem.`
- Header action: `Tambah Program`

#### CreateProgram
- Title: `Buat Program Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Program berhasil dibuat`

#### EditProgram
- Notifikasi simpan: `Data Program berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewProgram
- Title: `Detail {name}`
- Breadcrumb: `Data Program > Detail {name}`

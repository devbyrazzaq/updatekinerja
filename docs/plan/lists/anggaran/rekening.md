## Resource: `Rekening` (Rekening)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\Rekening`     |
| Navigation Group | `Anggaran`                |
| Navigation Label | `Rekening`                |
| Plural Label     | `Data Rekening`           |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

---

### Migration

| Kolom         | Tipe                       | Keterangan                            |
| ------------- | -------------------------- | ------------------------------------- |
| `id`          | `bigIncrements`            | Primary key                           |
| `code`        | `string`, `unique`         | Kode akun/rekening (mis. 5.1.02.01)   |
| `name`        | `string`                   | Nama rekening                         |
| `slug`        | `string`, `unique`         | Auto-generate dari `name`             |
| `description` | `text`, nullable           | Deskripsi                             |
| `is_active`   | `boolean`, default `true`  | Status aktif/nonaktif                 |
| `timestamps`  | —                          | `created_at`, `updated_at`            |

> `code` adalah "kode akun" yang dirujuk oleh Acuan & Penawaran Program Kerja.

---

### Model

- Fillable: `code`, `name`, `description`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `acuanProgramKerjas()` → `hasMany(AcuanProgramKerja::class)`
    - `penawaranProgramKerjas()` → `hasMany(PenawaranProgramKerja::class)`

---

### Form Fields

| Field         | Komponen    | Keterangan                                       |
| ------------- | ----------- | ------------------------------------------------ |
| `code`       | `TextInput` | Required, unique, label `Kode Akun`              |
| `name`       | `TextInput` | Required, label `Nama Rekening`, live on blur    |
| `description`| `Textarea`  | Nullable, label `Deskripsi`, `->columnSpanFull()`|
| `is_active`  | `Toggle`    | Label `Status Aktif`, default `true`             |

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
| `code`      | `TextEntry` | Label "Kode Akun"             |
| `name`      | `TextEntry` | Label "Nama Rekening"         |
| `description`| `TextEntry` | Label "Deskripsi"             |
| `is_active` | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`| `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`| `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListRekenings
- Title: `Data Rekening`
- Subheading: `Berikut adalah daftar Rekening yang tersedia dalam sistem.`
- Header action: `Tambah Rekening`

#### CreateRekening
- Title: `Buat Rekening Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Rekening berhasil dibuat`

#### EditRekening
- Notifikasi simpan: `Data Rekening berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewRekening
- Title: `Detail {name}`
- Breadcrumb: `Data Rekening > Detail {name}`

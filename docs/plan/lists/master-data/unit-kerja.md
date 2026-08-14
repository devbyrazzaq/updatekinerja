## Resource: `UnitKerja` (Unit Kerja)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\UnitKerja`    |
| Navigation Group | `Master Data`             |
| Navigation Label | `Unit Kerja`              |
| Plural Label     | `Data Unit Kerja`         |
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

> Relasi ke User (siapa yang tergabung di unit) ditangani via kolom `unit_kerja_id`
> pada tabel `users` (lihat spec Pengguna) — bukan pivot terpisah pada MVP.

---

### Model

- Fillable: `name`, `description`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `users()` → `hasMany(User::class)`
    - `paguAnggarans()` → `hasMany(PaguAnggaran::class)`
    - `acuanProgramKerjas()` → `hasMany(AcuanProgramKerja::class)`

---

### Form Fields

| Field         | Komponen    | Keterangan                                       |
| ------------- | ----------- | ------------------------------------------------ |
| `name`       | `TextInput` | Required, label `Nama Unit Kerja`, live on blur  |
| `description`| `Textarea`  | Nullable, label `Deskripsi`, `->columnSpanFull()` |
| `is_active`  | `Toggle`    | Label `Status Aktif`, default `true`             |

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
| `name`      | `TextEntry` | Label "Nama Unit Kerja"       |
| `description`| `TextEntry` | Label "Deskripsi"             |
| `is_active` | `IconEntry` | Boolean, label "Status Aktif" |
| `created_at`| `TextEntry` | Label "Dibuat", dateTime      |
| `updated_at`| `TextEntry` | Label "Diperbarui", dateTime  |

---

### Halaman (Pages)

#### ListUnitKerjas
- Title: `Data Unit Kerja`
- Subheading: `Berikut adalah daftar Unit Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Unit Kerja`

#### CreateUnitKerja
- Title: `Buat Unit Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Unit Kerja berhasil dibuat`

#### EditUnitKerja
- Notifikasi simpan: `Data Unit Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewUnitKerja
- Title: `Detail {name}`
- Breadcrumb: `Data Unit Kerja > Detail {name}`

## Resource: `AcuanProgramKerja` (Acuan Program Kerja)

### Informasi Dasar

| Properti         | Nilai                              |
| ---------------- | ---------------------------------- |
| Model            | `App\Models\AcuanProgramKerja`     |
| Navigation Group | `Program Kerja`                    |
| Navigation Label | `Acuan Program Kerja`              |
| Plural Label     | `Data Acuan Program Kerja`         |
| Navigation Icon  | _(tentukan yang sesuai)_           |
| Role Filter      | Tidak ada                          |
| Shared Component | Tidak (resource-specific)          |

---

### Migration

| Kolom                    | Tipe                                                             | Keterangan                       |
| ------------------------ | --------------------------------------------------------------- | -------------------------------- |
| `id`                     | `bigIncrements`                                                 | Primary key                      |
| `unit_kerja_id`          | `foreignId`->`constrained('unit_kerjas')`->`cascadeOnDelete()`  | Unit kerja                       |
| `bidang_id`              | `foreignId`->`constrained('bidangs')`->`cascadeOnDelete()`      | Bidang                           |
| `kategori_id`            | `foreignId`->`constrained('kategoris')`->`cascadeOnDelete()`    | Kategori                         |
| `program_id`             | `foreignId`->`constrained('programs')`->`cascadeOnDelete()`     | Program                          |
| `rekening_id`            | `foreignId`->`constrained('rekenings')`->`nullOnDelete()`, nullable | Kode akun                    |
| `name`                   | `string`                                                        | Nama program kerja               |
| `slug`                   | `string`, `unique`                                             | Auto-generate dari `name`        |
| `aktifitas`              | `text`, nullable                                              | Aktivitas                        |
| `indikator`             | `text`, nullable                                              | Indikator capaian                |
| `nilai_standar`          | `string`, nullable                                            | Nilai standar                    |
| `satuan_nilai_standar`   | `string`, nullable                                            | Satuan nilai standar             |
| `is_active`              | `boolean`, default `true`                                     | Status aktif/nonaktif            |
| `timestamps`             | —                                                            | `created_at`, `updated_at`       |

---

### Migration Tambahan: `acuan_targets`

| Kolom                     | Tipe                                                                     | Keterangan                    |
| ------------------------- | ------------------------------------------------------------------------ | ----------------------------- |
| `id`                      | `bigIncrements`                                                          | Primary key                   |
| `acuan_program_kerja_id`  | `foreignId`->`constrained('acuan_program_kerjas')`->`cascadeOnDelete()`  | Induk acuan                   |
| `tahun_kerja_id`          | `foreignId`->`constrained('tahun_kerjas')`->`cascadeOnDelete()`          | Tahun kerja                   |
| `target`                  | `string`                                                                 | Target untuk tahun kerja itu  |
| `timestamps`              | —                                                                        | `created_at`, `updated_at`    |

Catatan: unique gabungan (`acuan_program_kerja_id`, `tahun_kerja_id`). Tabel ini
mewujudkan "has many ke tahun kerja dan target" — satu acuan bisa punya target berbeda
tiap tahun kerja. Dikelola via Repeater di form Acuan.

---

### Model

- Fillable: `unit_kerja_id`, `bidang_id`, `kategori_id`, `program_id`, `rekening_id`, `name`, `aktifitas`, `indikator`, `nilai_standar`, `satuan_nilai_standar`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `unitKerja()` → `belongsTo(UnitKerja::class)`
    - `bidang()` → `belongsTo(Bidang::class)`
    - `kategori()` → `belongsTo(Kategori::class)`
    - `program()` → `belongsTo(Program::class)`
    - `rekening()` → `belongsTo(Rekening::class)`
    - `targets()` → `hasMany(AcuanTarget::class)`
    - `penawaranProgramKerjas()` → `hasMany(PenawaranProgramKerja::class)`

---

### Model Tambahan: `AcuanTarget`

- Fillable: `acuan_program_kerja_id`, `tahun_kerja_id`, `target`
- RouteKeyName: default `id`
- Relationships:
    - `acuanProgramKerja()` → `belongsTo(AcuanProgramKerja::class)`
    - `tahunKerja()` → `belongsTo(TahunKerja::class)`

---

### Form Fields

Layout: `Section` "Informasi Program Kerja" (Grid 2) + `Section` "Target per Tahun Kerja" (Repeater).

| Field                  | Komponen    | Keterangan                                                        |
| ---------------------- | ----------- | ---------------------------------------------------------------- |
| `name`                | `TextInput` | Required, label `Nama Program Kerja`, live on blur, `->columnSpanFull()` |
| `unit_kerja_id`       | `Select`    | Required, relationship `unitKerja` (`name`), searchable          |
| `bidang_id`           | `Select`    | Required, relationship `bidang` (`name`), searchable             |
| `kategori_id`         | `Select`    | Required, relationship `kategori` (`name`), searchable           |
| `program_id`          | `Select`    | Required, relationship `program` (`name`), searchable            |
| `rekening_id`         | `Select`    | Nullable, relationship `rekening` (`code`), label `Kode Akun`, searchable |
| `aktifitas`           | `Textarea`  | Nullable, label `Aktivitas`, `->columnSpanFull()`                |
| `indikator`          | `Textarea`  | Nullable, label `Indikator`, `->columnSpanFull()`                |
| `nilai_standar`       | `TextInput` | Nullable, label `Nilai Standar`                                  |
| `satuan_nilai_standar`| `TextInput` | Nullable, label `Satuan Nilai Standar`                          |
| `targets`             | `Repeater`  | relationship `targets`, label `Target per Tahun Kerja`, `->columnSpanFull()` |
| `is_active`           | `Toggle`    | Label `Status Aktif`, default `true`                             |

#### Field Repeater `targets`

| Field            | Komponen    | Keterangan                                                  |
| ---------------- | ----------- | ---------------------------------------------------------- |
| `tahun_kerja_id`| `Select`    | Required, relationship `tahunKerja` (`name`), searchable   |
| `target`        | `TextInput` | Required, label `Target`                                   |

Validasi tambahan:
- `tahun_kerja_id` unik antar item dalam satu acuan (tidak boleh duplikat tahun kerja).

---

### Table Columns

| Kolom            | Tipe         | Keterangan                          |
| ---------------- | ------------ | ----------------------------------- |
| `name`          | `TextColumn` | Searchable, sortable, wrap          |
| `unitKerja.name`| `TextColumn` | Label `Unit Kerja`, searchable, toggleable |
| `bidang.name`   | `TextColumn` | Label `Bidang`, toggleable          |
| `kategori.name` | `TextColumn` | Label `Kategori`, badge, toggleable |
| `program.name`  | `TextColumn` | Label `Program`, toggleable         |
| `targets_count` | `TextColumn` | Label `Jumlah Target`, `counts('targets')`, badge |
| `is_active`     | `ToggleColumn` | toggleable                        |
| `created_at`    | `TextColumn` | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter          | Tipe           | Keterangan                       |
| --------------- | -------------- | -------------------------------- |
| `unit_kerja_id` | `SelectFilter` | Relationship `unitKerja`         |
| `bidang_id`     | `SelectFilter` | Relationship `bidang`            |
| `kategori_id`   | `SelectFilter` | Relationship `kategori`          |
| `program_id`    | `SelectFilter` | Relationship `program`           |
| `is_active`     | `SelectFilter` | Filter status aktif/nonaktif     |

---

### Infolist Entries

| Entry                  | Tipe        | Keterangan                                   |
| ---------------------- | ----------- | -------------------------------------------- |
| `name`                | `TextEntry` | Label "Nama Program Kerja"                    |
| `unitKerja.name`      | `TextEntry` | Label "Unit Kerja"                           |
| `bidang.name`         | `TextEntry` | Label "Bidang"                               |
| `kategori.name`       | `TextEntry` | Label "Kategori"                             |
| `program.name`        | `TextEntry` | Label "Program"                              |
| `rekening.code`       | `TextEntry` | Label "Kode Akun"                            |
| `aktifitas`           | `TextEntry` | Label "Aktivitas"                            |
| `indikator`          | `TextEntry` | Label "Indikator"                            |
| `nilai_standar`       | `TextEntry` | Label "Nilai Standar"                        |
| `satuan_nilai_standar`| `TextEntry` | Label "Satuan Nilai Standar"                 |
| `is_active`           | `IconEntry` | Boolean, label "Status Aktif"                |
| `created_at`          | `TextEntry` | Label "Dibuat", dateTime                     |
| `updated_at`          | `TextEntry` | Label "Diperbarui", dateTime                 |

Target per tahun kerja ditampilkan via `RepeatableEntry` / RelationManager `targets`
(kolom Tahun Kerja + Target).

---

### Relation Manager

#### TargetsRelationManager — Target per Tahun Kerja
- Relationship: `targets`
- Record Title: `tahunKerja.name`
- Kolom table: `tahunKerja.name` (Tahun Kerja), `target` (Target)
- Actions: Create/Edit/Delete (tanpa CAPTCHA)

> Opsional: target juga dapat dikelola via Repeater di form. Pilih salah satu agar tidak
> ganda; default gunakan Repeater di form (lebih ringkas). Relation Manager sebagai alternatif.

---

### Halaman (Pages)

#### ListAcuanProgramKerjas
- Title: `Data Acuan Program Kerja`
- Subheading: `Berikut adalah daftar Acuan Program Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Acuan Program Kerja`

#### CreateAcuanProgramKerja
- Title: `Buat Acuan Program Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Acuan Program Kerja berhasil dibuat`

#### EditAcuanProgramKerja
- Notifikasi simpan: `Data Acuan Program Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewAcuanProgramKerja
- Title: `Detail {name}`
- Breadcrumb: `Data Acuan Program Kerja > Detail {name}`

---

### Acceptance Criteria

- Satu Acuan dapat memiliki banyak target, satu per Tahun Kerja (tidak duplikat tahun).
- Acuan menjadi sumber pembentukan Penawaran Program Kerja per Tahun Kerja.

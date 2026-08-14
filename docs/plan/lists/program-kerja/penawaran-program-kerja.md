## Resource: `PenawaranProgramKerja` (Penawaran Program Kerja)

### Informasi Dasar

| Properti         | Nilai                                |
| ---------------- | ------------------------------------ |
| Model            | `App\Models\PenawaranProgramKerja`   |
| Navigation Group | `Program Kerja`                      |
| Navigation Label | `Penawaran Program Kerja`            |
| Plural Label     | `Data Penawaran Program Kerja`       |
| Navigation Icon  | _(tentukan yang sesuai)_             |
| Role Filter      | Tidak ada                            |
| Shared Component | Tidak (resource-specific)            |

---

### Migration

| Kolom                    | Tipe                                                                          | Keterangan                   |
| ------------------------ | ----------------------------------------------------------------------------- | ---------------------------- |
| `id`                     | `bigIncrements`                                                               | Primary key                  |
| `acuan_program_kerja_id` | `foreignId`->`constrained('acuan_program_kerjas')`->`nullOnDelete()`, nullable | Sumber acuan (opsional)     |
| `tahun_kerja_id`         | `foreignId`->`constrained('tahun_kerjas')`->`cascadeOnDelete()`               | Tahun kerja penawaran        |
| `unit_kerja_id`          | `foreignId`->`constrained('unit_kerjas')`->`cascadeOnDelete()`                | Unit kerja                   |
| `bidang_id`              | `foreignId`->`constrained('bidangs')`->`cascadeOnDelete()`                    | Bidang                       |
| `kategori_id`            | `foreignId`->`constrained('kategoris')`->`cascadeOnDelete()`                  | Kategori                     |
| `program_id`             | `foreignId`->`constrained('programs')`->`cascadeOnDelete()`                   | Program                      |
| `rekening_id`            | `foreignId`->`constrained('rekenings')`->`nullOnDelete()`, nullable           | Kode akun                    |
| `name`                   | `string`                                                                      | Nama program kerja           |
| `slug`                   | `string`, `unique`                                                           | Auto-generate dari `name`    |
| `aktifitas`              | `text`, nullable                                                            | Aktivitas                    |
| `indikator`             | `text`, nullable                                                            | Indikator                    |
| `nilai_standar`          | `string`, nullable                                                          | Nilai standar                |
| `satuan_nilai_standar`   | `string`, nullable                                                          | Satuan nilai standar         |
| `target`                 | `string`, nullable                                                          | Target tahun kerja tsb.      |
| `is_active`              | `boolean`, default `true`                                                   | Status aktif/nonaktif        |
| `timestamps`             | —                                                                          | `created_at`, `updated_at`   |

---

### Model

- Fillable: `acuan_program_kerja_id`, `tahun_kerja_id`, `unit_kerja_id`, `bidang_id`, `kategori_id`, `program_id`, `rekening_id`, `name`, `aktifitas`, `indikator`, `nilai_standar`, `satuan_nilai_standar`, `target`, `is_active`
- Cast: `is_active` → `boolean`
- Trait: `Spatie\Sluggable\HasSlug`
- RouteKeyName: `slug`
- Relationships:
    - `acuanProgramKerja()` → `belongsTo(AcuanProgramKerja::class)`
    - `tahunKerja()` → `belongsTo(TahunKerja::class)`
    - `unitKerja()` → `belongsTo(UnitKerja::class)`
    - `bidang()` → `belongsTo(Bidang::class)`
    - `kategori()` → `belongsTo(Kategori::class)`
    - `program()` → `belongsTo(Program::class)`
    - `rekening()` → `belongsTo(Rekening::class)`
    - `pengajuanProgramKerjas()` → `hasMany(PengajuanProgramKerja::class)`

---

### Form Fields

`acuan_program_kerja_id` bersifat live: memilih acuan mengisi otomatis field lain
(`->afterStateUpdated`), lalu `tahun_kerja_id` menentukan `target` dari `AcuanTarget`.

| Field                    | Komponen    | Keterangan                                                                 |
| ------------------------ | ----------- | ------------------------------------------------------------------------- |
| `acuan_program_kerja_id`| `Select`    | Nullable, relationship `acuanProgramKerja` (`name`), searchable, live; isi field lain saat dipilih |
| `tahun_kerja_id`        | `Select`    | Required, relationship `tahunKerja` (`name`), default tahun kerja aktif, live |
| `name`                  | `TextInput` | Required, label `Nama Program Kerja`, `->columnSpanFull()`                 |
| `unit_kerja_id`         | `Select`    | Required, relationship `unitKerja` (`name`), searchable                    |
| `bidang_id`             | `Select`    | Required, relationship `bidang` (`name`), searchable                       |
| `kategori_id`           | `Select`    | Required, relationship `kategori` (`name`), searchable                     |
| `program_id`            | `Select`    | Required, relationship `program` (`name`), searchable                      |
| `rekening_id`           | `Select`    | Nullable, relationship `rekening` (`code`), label `Kode Akun`, searchable  |
| `aktifitas`             | `Textarea`  | Nullable, label `Aktivitas`, `->columnSpanFull()`                         |
| `indikator`            | `Textarea`  | Nullable, label `Indikator`, `->columnSpanFull()`                         |
| `nilai_standar`         | `TextInput` | Nullable, label `Nilai Standar`                                           |
| `satuan_nilai_standar`  | `TextInput` | Nullable, label `Satuan Nilai Standar`                                    |
| `target`                | `TextInput` | Nullable, label `Target`                                                  |
| `is_active`             | `Toggle`    | Label `Status Aktif`, default `true`                                      |

---

### Table Columns

| Kolom             | Tipe         | Keterangan                          |
| ----------------- | ------------ | ----------------------------------- |
| `name`           | `TextColumn` | Searchable, sortable, wrap          |
| `tahunKerja.name`| `TextColumn` | Label `Tahun Kerja`, searchable, sortable |
| `unitKerja.name` | `TextColumn` | Label `Unit Kerja`, toggleable      |
| `kategori.name`  | `TextColumn` | Label `Kategori`, badge, toggleable |
| `target`         | `TextColumn` | Label `Target`, toggleable          |
| `is_active`      | `ToggleColumn` | toggleable                        |
| `created_at`     | `TextColumn` | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter           | Tipe           | Keterangan                       |
| ---------------- | -------------- | -------------------------------- |
| `tahun_kerja_id` | `SelectFilter` | Relationship `tahunKerja`, default tahun kerja aktif |
| `unit_kerja_id`  | `SelectFilter` | Relationship `unitKerja`         |
| `kategori_id`    | `SelectFilter` | Relationship `kategori`          |
| `is_active`      | `SelectFilter` | Filter status aktif/nonaktif     |

---

### Infolist Entries

| Entry                  | Tipe        | Keterangan                       |
| ---------------------- | ----------- | -------------------------------- |
| `name`                | `TextEntry` | Label "Nama Program Kerja"       |
| `tahunKerja.name`     | `TextEntry` | Label "Tahun Kerja"              |
| `unitKerja.name`      | `TextEntry` | Label "Unit Kerja"              |
| `bidang.name`         | `TextEntry` | Label "Bidang"                   |
| `kategori.name`       | `TextEntry` | Label "Kategori"                 |
| `program.name`        | `TextEntry` | Label "Program"                  |
| `rekening.code`       | `TextEntry` | Label "Kode Akun"                |
| `aktifitas`           | `TextEntry` | Label "Aktivitas"                |
| `indikator`          | `TextEntry` | Label "Indikator"                |
| `nilai_standar`       | `TextEntry` | Label "Nilai Standar"            |
| `satuan_nilai_standar`| `TextEntry` | Label "Satuan Nilai Standar"     |
| `target`              | `TextEntry` | Label "Target"                   |
| `is_active`           | `IconEntry` | Boolean, label "Status Aktif"    |
| `created_at`          | `TextEntry` | Label "Dibuat", dateTime         |
| `updated_at`          | `TextEntry` | Label "Diperbarui", dateTime     |

---

### Halaman (Pages)

#### ListPenawaranProgramKerjas
- Title: `Data Penawaran Program Kerja`
- Subheading: `Berikut adalah daftar Penawaran Program Kerja yang tersedia dalam sistem.`
- Header action: `Tambah Penawaran Program Kerja`

#### CreatePenawaranProgramKerja
- Title: `Buat Penawaran Program Kerja Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Penawaran Program Kerja berhasil dibuat`

#### EditPenawaranProgramKerja
- Notifikasi simpan: `Data Penawaran Program Kerja berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewPenawaranProgramKerja
- Title: `Detail {name}`
- Breadcrumb: `Data Penawaran Program Kerja > Detail {name}`

---

### Catatan Implementasi

- Penawaran = perwujudan Acuan untuk satu Tahun Kerja. Header action
  **"Generate dari Acuan"** (SUDAH DIIMPLEMENTASI): memilih Tahun Kerja + opsi
  "hanya acuan bertarget", lalu membentuk Penawaran massal dari seluruh Acuan aktif
  beserta target tahun itu. Logika di `App\Services\GeneratePenawaranFromAcuan`
  (idempotent — `updateOrCreate` per acuan+tahun kerja+unit).
- Saat memilih `acuan_program_kerja_id`, salin `name`, relasi, dan nilai standar dari
  acuan; `target` diambil dari `AcuanTarget` yang cocok dengan `tahun_kerja_id`.

### Acceptance Criteria

- Memilih Acuan di form mengisi otomatis field program kerja dan target sesuai tahun kerja.
- Unit kerja melihat Penawaran aktif tahun kerja aktif di menu Daftar Program Kerja.

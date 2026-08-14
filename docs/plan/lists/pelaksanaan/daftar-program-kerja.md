## Resource: `PenawaranProgramKerja` (Daftar Program Kerja) — Resource kedua, read-only

### Informasi Dasar

| Properti         | Nilai                                       |
| ---------------- | ------------------------------------------- |
| Model            | `App\Models\PenawaranProgramKerja`          |
| Navigation Group | `Pelaksanaan`                               |
| Navigation Label | `Daftar Program Kerja`                       |
| Plural Label     | `Daftar Program Kerja`                       |
| Navigation Icon  | _(tentukan yang sesuai)_                     |
| Role Filter      | Unit Kerja hanya melihat penawaran unit-nya |
| Shared Component | Tidak (resource-specific)                   |

> Ini **Resource kedua atas model `PenawaranProgramKerja`** (bukan model baru). Tujuannya
> memberi unit kerja tampilan katalog penawaran + tombol "Ajukan". Class resource:
> `App\Filament\Resources\DaftarProgramKerja\DaftarProgramKerjaResource` dengan
> `getModel()` = `PenawaranProgramKerja::class`.

---

### Migration
Tidak ada — memakai tabel `penawaran_program_kerjas`.

---

### Model
Tidak ada model baru. Query dibatasi `getEloquentQuery()`:
- hanya `is_active = true` dan `tahun_kerja_id = TahunKerja::active()?->id`;
- untuk user non-privileged: `unit_kerja_id = auth()->user()->unit_kerja_id`.

---

### Table Columns

| Kolom             | Tipe         | Keterangan                          |
| ----------------- | ------------ | ----------------------------------- |
| `name`           | `TextColumn` | Searchable, sortable, wrap          |
| `kategori.name`  | `TextColumn` | Label `Kategori`, badge             |
| `program.name`   | `TextColumn` | Label `Program`, toggleable         |
| `target`         | `TextColumn` | Label `Target`                      |
| `rekening.code`  | `TextColumn` | Label `Kode Akun`, toggleable       |

Record action: **Ajukan** (`Filament\Actions\Action`) → membuka form Pengajuan
(prefill `penawaran_program_kerja_id`), lihat spec Pengajuan. Disembunyikan jika
penawaran sudah diajukan oleh unit tsb. Hanya View + Ajukan (tanpa Create/Edit/Delete).

---

### Table Filters

| Filter        | Tipe           | Keterangan               |
| ------------- | -------------- | ------------------------ |
| `kategori_id` | `SelectFilter` | Relationship `kategori`  |
| `program_id`  | `SelectFilter` | Relationship `program`   |

---

### Infolist Entries
Sama dengan Penawaran Program Kerja (read-only detail).

---

### Halaman (Pages)

#### ListDaftarProgramKerjas
- Title: `Daftar Program Kerja`
- Subheading: `Program kerja yang ditawarkan untuk tahun kerja aktif. Ajukan untuk mengusulkan pelaksanaan.`
- Tanpa header action Create.

#### ViewDaftarProgramKerja
- Title: `Detail {name}`
- Breadcrumb: `Daftar Program Kerja > Detail {name}`

---

### Catatan Implementasi

- Resource ini `canCreate()=false`, `canEdit()=false`, `canDelete()=false`. Hanya baca + aksi Ajukan.
- Tombol "Ajukan" membuat record `PengajuanProgramKerja` berstatus `Draft`/`Diajukan`.

### Acceptance Criteria

- Unit kerja hanya melihat penawaran aktif milik unit-nya pada tahun kerja aktif.
- Menekan "Ajukan" mengarahkan ke pembuatan Pengajuan untuk penawaran tersebut.

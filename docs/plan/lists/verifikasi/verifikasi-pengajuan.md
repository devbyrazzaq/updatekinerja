## Resource: `PengajuanProgramKerja` (Verifikasi Pengajuan) — Resource verifikasi tahap 1

### Informasi Dasar

| Properti         | Nilai                                    |
| ---------------- | ---------------------------------------- |
| Model            | `App\Models\PengajuanProgramKerja`       |
| Navigation Group | `Verifikasi Pengajuan`                   |
| Navigation Label | `Verifikasi Pengajuan`                   |
| Plural Label     | `Verifikasi Pengajuan`                   |
| Navigation Icon  | _(tentukan yang sesuai)_                 |
| Role Filter      | Hanya verifikator tahap 1 (permission)   |
| Shared Component | Tidak (resource-specific)                |

> Resource kedua atas `PengajuanProgramKerja`. Class:
> `App\Filament\Resources\VerifikasiPengajuan\VerifikasiPengajuanResource`.
> `getEloquentQuery()`: hanya status `Diajukan` (antrean verifikasi). Read-only kecuali
> action verifikasi. `canCreate/Edit/Delete = false`.

---

### Table Columns

| Kolom                          | Tipe         | Keterangan                              |
| ------------------------------ | ------------ | --------------------------------------- |
| `penawaranProgramKerja.name`  | `TextColumn` | Label `Program Kerja`, searchable, wrap |
| `unitKerja.name`              | `TextColumn` | Label `Unit Kerja`, searchable          |
| `alokasi_anggaran`            | `TextColumn` | money `IDR`, label `Pengajuan Anggaran` |
| `user.name`                   | `TextColumn` | Label `Pengaju`, toggleable             |
| `created_at`                  | `TextColumn` | Label `Diajukan`, dateTime, sortable    |

---

### Actions (record)

Satu action **Verifikasi** (`Filament\Actions\Action`) dengan schema modal:

| Field       | Komponen    | Keterangan                                                        |
| ----------- | ----------- | ---------------------------------------------------------------- |
| `hasil`    | `Radio`/`Select` | Required, options `EnumHasilVerifikasi` (Disetujui/Revisi/Ditolak) |
| `catatan`  | `Textarea`  | Nullable (wajib bila Revisi/Ditolak), label `Catatan`            |

`->action()`:
- set `verifikator_id = auth id`, `diverifikasi_at = now`, `catatan_verifikasi = catatan`;
- `Setuju` → status `Diterima`; `Revisi` → status `Revisi`; `Tolak` → status `Ditolak`;
- notifikasi sukses; record keluar dari antrean.

Tambahan: tombol **Detail** (View infolist Pengajuan, read-only).

---

### Infolist Entries
Sama dengan Pengajuan Program Kerja (read-only).

---

### Halaman (Pages)

#### ListVerifikasiPengajuans
- Title: `Verifikasi Pengajuan`
- Subheading: `Pengajuan program kerja yang menunggu verifikasi tahap 1.`
- Tanpa header action Create.
- Empty state: `Tidak ada pengajuan yang menunggu verifikasi.`

---

### Acceptance Criteria

- Hanya pengajuan berstatus `Diajukan` yang tampil.
- Verifikator dapat menyetujui (→ `Diterima`), merevisi (→ `Revisi`), atau menolak (→ `Ditolak`).
- Saat `Revisi`, unit dapat mengedit lalu mengajukan ulang.

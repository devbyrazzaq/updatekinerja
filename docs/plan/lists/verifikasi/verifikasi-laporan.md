## Resource: `RealisasiProgramKerja` (Verifikasi Laporan)

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\RealisasiProgramKerja`     |
| Navigation Group | `Verifikasi Realisasi`                 |
| Navigation Label | `Verifikasi Laporan`                   |
| Plural Label     | `Verifikasi Laporan`                   |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Hanya Verifikator Laporan (permission) |
| Shared Component | Tidak (resource-specific)              |

> `getEloquentQuery()`: status `VerifikasiLaporan` (laporan sudah diunggah unit).
> `canCreate/Edit/Delete=false`.

---

### Table Columns

| Kolom                                      | Tipe         | Keterangan                          |
| ------------------------------------------ | ------------ | ----------------------------------- |
| `name`                                    | `TextColumn` | Label `Kegiatan`, searchable, wrap  |
| `pengajuanProgramKerja.unitKerja.name`    | `TextColumn` | Label `Unit Kerja`                  |
| `anggaran_digunakan`                      | `TextColumn` | money `IDR`, label `Digunakan`      |
| `laporan_diserahkan_at`                   | `TextColumn` | dateTime, label `Laporan Diserahkan`, sortable |

---

### Actions (record)

- **Lihat Laporan** — unduh `laporan_path` via `temporaryUrl()` (private disk).
- **Verifikasi Laporan** (modal):

| Field       | Komponen    | Keterangan                                              |
| ----------- | ----------- | ------------------------------------------------------ |
| `hasil`    | `Radio`     | Required, options Disetujui / Revisi                   |
| `catatan`  | `Textarea`  | Nullable (wajib bila Revisi)                           |

`->action()`:
- `verifikator_laporan_id = auth id`;
- `Setuju` → `laporan_disetujui_at = now`, status `Selesai` (realisasi selesai);
- `Revisi` → status `Revisi` (unit perbaiki & unggah ulang).

Tambahan: **Detail** (View infolist Realisasi).

---

### Acceptance Criteria

- Hanya realisasi yang laporannya sudah diunggah (`VerifikasiLaporan`) yang tampil.
- Menyetujui laporan menyelesaikan realisasi (status `Selesai`).
- Revisi mengembalikan realisasi ke unit untuk diperbaiki.

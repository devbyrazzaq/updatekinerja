## Resource: `RealisasiProgramKerja` (Verifikasi Wakil Rektor)

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\RealisasiProgramKerja`     |
| Navigation Group | `Verifikasi Realisasi`                 |
| Navigation Label | `Verifikasi Wakil Rektor`              |
| Plural Label     | `Verifikasi Wakil Rektor`              |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Hanya Wakil Rektor (permission)        |
| Shared Component | Tidak (resource-specific)              |

> `getEloquentQuery()`: status `VerifikasiWakil`. `canCreate/Edit/Delete=false`.

---

### Table Columns

| Kolom                                      | Tipe         | Keterangan                          |
| ------------------------------------------ | ------------ | ----------------------------------- |
| `name`                                    | `TextColumn` | Label `Kegiatan`, searchable, wrap  |
| `pengajuanProgramKerja.unitKerja.name`    | `TextColumn` | Label `Unit Kerja`                  |
| `anggaran_digunakan`                      | `TextColumn` | money `IDR`, label `Digunakan`      |
| `nominal_disetujui_rektor`                | `TextColumn` | money `IDR`, label `ACC Rektor`     |
| `created_at`                              | `TextColumn` | Label `Diajukan`, dateTime          |

---

### Actions (record)

Action **Verifikasi Wakil Rektor** (modal):

| Field       | Komponen    | Keterangan                                                              |
| ----------- | ----------- | ---------------------------------------------------------------------- |
| `hasil`    | `Radio`     | Required, `EnumHasilVerifikasi`                                        |
| `nominal_disetujui_wakil` | `TextInput` | **Wajib bila belum ada nominal**, numeric, prefix `Rp`, default dari nominal rektor |
| `catatan`  | `Textarea`  | Nullable (wajib bila Revisi/Ditolak)                                   |

`->action()`:
- `wakil_id = auth id`, `disetujui_wakil_at = now`, simpan `nominal_disetujui_wakil`;
- validasi: jika `nominal_disetujui_rektor` dan `nominal_disetujui_wakil` keduanya kosong saat `Setuju` → tolak submit dengan pesan wajib mengisi nominal;
- `Setuju` → status `VerifikasiKeuangan`; `Revisi` → `Revisi`; `Tolak` → `Ditolak`.

Tambahan: **Detail** (View infolist Realisasi).

---

### Halaman (Pages)

#### ListVerifikasiWakilRektors
- Title: `Verifikasi Wakil Rektor`
- Subheading: `Realisasi program kerja yang menunggu persetujuan Wakil Rektor.`
- Empty state: `Tidak ada realisasi yang menunggu verifikasi Wakil Rektor.`

---

### Acceptance Criteria

- Nominal disetujui wajib terisi sebelum status berpindah ke Biro Keuangan.
- Setuju meneruskan ke Biro Keuangan.

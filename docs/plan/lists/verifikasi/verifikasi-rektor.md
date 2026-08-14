## Resource: `RealisasiProgramKerja` (Verifikasi Rektor) — verifikasi realisasi tahap Rektor

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\RealisasiProgramKerja`     |
| Navigation Group | `Verifikasi Realisasi`                 |
| Navigation Label | `Verifikasi Rektor`                    |
| Plural Label     | `Verifikasi Rektor`                    |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Hanya Rektor (permission)              |
| Shared Component | Tidak (resource-specific)              |

> Resource atas `RealisasiProgramKerja`. `getEloquentQuery()`: status
> `Diajukan` atau `VerifikasiRektor` (antrean rektor). `canCreate/Edit/Delete=false`.

---

### Table Columns

| Kolom                                              | Tipe         | Keterangan                          |
| -------------------------------------------------- | ------------ | ----------------------------------- |
| `name`                                            | `TextColumn` | Label `Kegiatan`, searchable, wrap  |
| `pengajuanProgramKerja.unitKerja.name`            | `TextColumn` | Label `Unit Kerja`, searchable      |
| `pengajuanProgramKerja.alokasi_anggaran`          | `TextColumn` | money `IDR`, label `Alokasi`        |
| `anggaran_digunakan`                              | `TextColumn` | money `IDR`, label `Digunakan`      |
| `created_at`                                      | `TextColumn` | Label `Diajukan`, dateTime          |

---

### Actions (record)

Action **Verifikasi Rektor** (modal):

| Field       | Komponen    | Keterangan                                              |
| ----------- | ----------- | ------------------------------------------------------ |
| `hasil`    | `Radio`     | Required, `EnumHasilVerifikasi`                        |
| `nominal_disetujui_rektor` | `TextInput` | **Opsional**, numeric, prefix `Rp`, label `Nominal Disetujui` |
| `catatan`  | `Textarea`  | Nullable (wajib bila Revisi/Ditolak)                  |

`->action()`:
- `rektor_id = auth id`, `disetujui_rektor_at = now`, simpan `nominal_disetujui_rektor` bila diisi;
- `Setuju` → status `VerifikasiWakil`; `Revisi` → `Revisi`; `Tolak` → `Ditolak`.

Tambahan: **Detail** (View infolist Realisasi).

---

### Halaman (Pages)

#### ListVerifikasiRektors
- Title: `Verifikasi Rektor`
- Subheading: `Realisasi program kerja yang menunggu persetujuan Rektor.`
- Empty state: `Tidak ada realisasi yang menunggu verifikasi Rektor.`

---

### Acceptance Criteria

- Hanya realisasi tahap Rektor yang tampil.
- Nominal disetujui bersifat opsional pada tahap ini.
- Setuju meneruskan ke Wakil Rektor; Revisi mengembalikan ke unit; Tolak final.

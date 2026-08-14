## Resource: `RealisasiProgramKerja` (Verifikasi Biro Keuangan)

### Informasi Dasar

| Properti         | Nilai                                  |
| ---------------- | -------------------------------------- |
| Model            | `App\Models\RealisasiProgramKerja`     |
| Navigation Group | `Verifikasi Realisasi`                 |
| Navigation Label | `Verifikasi Biro Keuangan`             |
| Plural Label     | `Verifikasi Biro Keuangan`             |
| Navigation Icon  | _(tentukan yang sesuai)_               |
| Role Filter      | Hanya Biro Keuangan (permission)       |
| Shared Component | Tidak (resource-specific)              |

> `getEloquentQuery()`: status `VerifikasiKeuangan`, `Dijadwalkan`, atau `MenungguLaporan`
> (yang belum menyerahkan laporan). `canCreate/Edit/Delete=false`.

---

### Halaman (Pages) — dua tab

Halaman list memakai **Tabs** (`getTabs()` Filament):
1. **Tab "Perlu Diproses"** — status `VerifikasiKeuangan` (belum dijadwalkan/diproses).
2. **Tab "Jadwal Pencairan"** — status `Dijadwalkan`/`MenungguLaporan`, diurutkan
   `jadwal_pencairan` — memenuhi catatan konsep "tab jadwal Pencairan anggaran".

---

### Table Columns

| Kolom                                      | Tipe         | Keterangan                          |
| ------------------------------------------ | ------------ | ----------------------------------- |
| `name`                                    | `TextColumn` | Label `Kegiatan`, searchable, wrap  |
| `pengajuanProgramKerja.unitKerja.name`    | `TextColumn` | Label `Unit Kerja`                  |
| `nominal_disetujui_wakil`                 | `TextColumn` | money `IDR`, label `Nominal Disetujui` |
| `status_pencairan`                        | `TextColumn` | badge, label `Status Pencairan`     |
| `jadwal_pencairan`                        | `TextColumn` | date, label `Jadwal Pencairan`, sortable |
| `dicairkan_at`                            | `TextColumn` | dateTime, label `Dicairkan`, toggleable |

---

### Actions (record)

Action **Proses Pencairan** (modal), visible pada tab "Perlu Diproses":

| Field                | Komponen         | Keterangan                                                     |
| -------------------- | ---------------- | -------------------------------------------------------------- |
| `status_pencairan`  | `Radio`          | Required, options `EnumStatusPencairan` (Dijadwalkan/Menunggu Laporan) |
| `jadwal_pencairan`  | `DatePicker`     | **Wajib bila status = Menunggu Laporan** (harus menjadwalkan kapan dicairkan) |
| `catatan`           | `Textarea`       | Nullable                                                       |

`->action()`:
- `keuangan_id = auth id`, simpan `status_pencairan` & `jadwal_pencairan`;
- `Dijadwalkan` → status realisasi `Dijadwalkan`;
- `MenungguLaporan` → **wajib** `jadwal_pencairan` terisi; status realisasi `MenungguLaporan`.

Action **Tandai Dicairkan** (tab Jadwal Pencairan): set `dicairkan_at = now`,
`status_pencairan = Dicairkan` — memberi info uang sudah diterima. Bila belum
`MenungguLaporan`, ubah status realisasi ke `MenungguLaporan` agar unit mengunggah laporan.

Tambahan: **Detail** (View infolist Realisasi).

---

### Acceptance Criteria

- Memilih status "Menunggu Laporan" mewajibkan pengisian `jadwal_pencairan`.
- Tab "Jadwal Pencairan" menampilkan realisasi terjadwal beserta tanggalnya.
- Setelah dicairkan, realisasi berstatus `MenungguLaporan` menanti unggahan laporan unit.

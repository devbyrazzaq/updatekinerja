## Resource: `Pemasukan` (Pemasukan Unit) — perubahan jenis waktu & alur pengajuan

Perubahan atas Resource yang **sudah ada** di `app/Filament/Resources/Pemasukans/`.
Resource verifikasinya dijelaskan di [`verifikasi-pemasukan.md`](verifikasi-pemasukan.md).

### Informasi Dasar

| Properti         | Nilai                                     |
| ---------------- | ----------------------------------------- |
| Model            | `App\Models\Pemasukan`                    |
| Navigation Group | `Pemasukan` _(tetap)_                     |
| Navigation Label | `Pemasukan Unit` _(tetap)_                |
| Pemilik alur     | Unit kerja (pengaju)                      |
| Data scope       | `PermissionRegistrar::permittedUnitIds()` _(tetap)_ |

---

## 1. Migration

### `add_jenis_waktu_to_pemasukans_table`

| Kolom             | Tipe               | Keterangan                                        |
| ----------------- | ------------------ | ------------------------------------------------- |
| `jenis_waktu`     | `string`           | default `'satu_hari'`, `after('rincian_kegiatan')` |
| `tanggal_selesai` | `date` nullable    | `after('tanggal_pelaksanaan')`, diisi hanya bila rentang |

### `add_verifikasi_to_pemasukans_table`

| Kolom                   | Tipe                     | Keterangan                          |
| ----------------------- | ------------------------ | ----------------------------------- |
| `status`                | `string` default `draft` | diberi index                        |
| `user_id`               | FK `users` nullOnDelete  | pengaju — dipakai notifikasi revisi/tolak |
| `catatan_verifikasi`    | `text` nullable          | catatan revisi/penolakan terakhir   |
| `disetujui_rektor_at`   | `dateTime` nullable      |                                     |
| `rektor_id`             | FK `users` nullOnDelete  |                                     |
| `disetujui_wakil_at`    | `dateTime` nullable      |                                     |
| `wakil_id`              | FK `users` nullOnDelete  |                                     |
| `disetujui_keuangan_at` | `dateTime` nullable      |                                     |
| `keuangan_id`           | FK `users` nullOnDelete  |                                     |
| `bukti_path`            | `json` nullable          | path berkas bukti tanda terima      |
| `bukti_original_names`  | `json` nullable          | nama asli berkas (`storeFileNamesIn`) |
| `bukti_diserahkan_at`   | `dateTime` nullable      |                                     |
| `divalidasi_at`         | `dateTime` nullable      | saat status menjadi Valid           |

> **WAJIB — backfill data lama.** Setelah kolom dibuat, seluruh baris `pemasukans`
> yang sudah ada di-update menjadi `status = 'valid'` dan `divalidasi_at = now()`.
> Tanpa ini, semua pemasukan lama berstatus `draft` dan **hilang dari Buku Anggaran**
> begitu penyaringan status diaktifkan.
>
> `down()` cukup `dropColumn` (data status tidak perlu dipulihkan).

### `create_pemasukan_logs_table`

Meniru `realisasi_program_kerja_logs`:

| Kolom          | Tipe                          |
| -------------- | ----------------------------- |
| `id`           | `id`                          |
| `pemasukan_id` | FK `pemasukans` cascadeOnDelete |
| `user_id`      | FK `users` nullOnDelete       |
| `status`       | `string`                      |
| `description`  | `text`                        |
| `properties`   | `json` nullable               |
| `timestamps`   |                               |

---

## 2. Enums

### `EnumJenisWaktuPemasukan` (`app/Enums/`)

`implements HasLabel`

| Case      | Value        | Label            |
| --------- | ------------ | ---------------- |
| `SatuHari` | `satu_hari` | `1 Hari`         |
| `Rentang`  | `rentang`   | `Rentang Waktu`  |

Method tambahan: `isRentang(): bool`.

### `EnumStatusPemasukan` (`app/Enums/`)

`implements HasColor, HasLabel` — meniru `EnumStatusRealisasi`.

| Case                | Value                 | Label                      | Color     |
| ------------------- | --------------------- | -------------------------- | --------- |
| `Draft`             | `draft`               | `Draf`                     | `gray`    |
| `Diajukan`          | `diajukan`            | `Diajukan`                 | `info`    |
| `VerifikasiRektor`  | `verifikasi_rektor`   | `Verifikasi Rektor`        | `info`    |
| `VerifikasiWakil`   | `verifikasi_wakil`    | `Verifikasi Wakil Rektor`  | `info`    |
| `VerifikasiKeuangan`| `verifikasi_keuangan` | `Verifikasi Biro Keuangan` | `info`    |
| `MenungguBukti`     | `menunggu_bukti`      | `Menunggu Bukti Tanda Terima` | `warning` |
| `Valid`             | `valid`               | `Valid`                    | `success` |
| `Revisi`            | `revisi`              | `Revisi`                   | `warning` |
| `Ditolak`           | `ditolak`             | `Ditolak`                  | `danger`  |

Method:

- `tahapan(): EnumTahapanPemasukan` — `Draft`/`Revisi` → `Draf`;
  `Diajukan`/`VerifikasiRektor`/`Ditolak` → `VerifikasiRektor`; dst.
- `statusCabang(): array` — `[Revisi, Ditolak]`.
- `isBerjalan(): bool` — sudah diajukan, belum Valid maupun Ditolak.

### `EnumTahapanPemasukan` (`app/Enums/`)

Meniru `EnumTahapanRealisasi` (`getLabel()`, `description()`, `flowCases()`,
`order()`, `isBefore()`, `isAfter()`).

| Case                 | Label                       |
| -------------------- | --------------------------- |
| `Draf`               | `Pencatatan Pemasukan`      |
| `VerifikasiRektor`   | `Verifikasi Rektor`         |
| `VerifikasiWakil`    | `Verifikasi Wakil Rektor`   |
| `VerifikasiKeuangan` | `Verifikasi Biro Keuangan`  |
| `BuktiTerima`        | `Bukti Tanda Terima`        |
| `Valid`              | `Valid`                     |

---

## 3. Model `Pemasukan`

Tambahan pada `$fillable` dan `casts()`:

```php
'jenis_waktu' => EnumJenisWaktuPemasukan::class,
'status' => EnumStatusPemasukan::class,
'tanggal_selesai' => 'date',
'bukti_path' => 'array',
'bukti_original_names' => 'array',
'disetujui_rektor_at' => 'datetime',
'disetujui_wakil_at' => 'datetime',
'disetujui_keuangan_at' => 'datetime',
'bukti_diserahkan_at' => 'datetime',
'divalidasi_at' => 'datetime',
```

### Relasi baru

`pengaju()` (`user_id`), `rektor()`, `wakil()`, `keuangan()`, `logs()` (`HasMany<PemasukanLog>`).

### Method baru

| Method | Keterangan |
| ------ | ---------- |
| `labelPeriode(): string` | `12 Agustus 2026` bila 1 hari; `12 – 15 Agustus 2026` bila rentang (bulan/tahun tidak diulang bila sama). Dipakai tabel, infolist, dan export. |
| `catatLog(EnumStatusPemasukan $status, ?int $userId, ?string $catatan = null, bool $diajukanKembali = false): PemasukanLog` | Kalimat naratif per status, meniru `RealisasiProgramKerja::catatLog()` |
| `statusSebelumCabang(): ?EnumStatusPemasukan` | Status terakhir sebelum Revisi/Ditolak, dibaca dari log |
| `tahapanStepper(): EnumTahapanPemasukan` | Status cabang mengambil tahap dari `statusSebelumCabang()` |
| `statusTujuanPengajuan(): EnumStatusPemasukan` | Draf → `Diajukan`; Revisi → kembali ke tahap peminta revisi |
| `labelStatus(): string` | Revisi diperjelas: `Revisi Rektor`, `Revisi Wakil Rektor`, `Revisi Biro Keuangan` |
| `dapatDiajukan(): bool` | Status `Draft` atau `Revisi` |
| `dapatDiubah(): bool` | Status `Draft` atau `Revisi` — dasar `canEdit`/`canDelete` |

### Model `PemasukanLog`

Meniru `RealisasiProgramKerjaLog`, termasuk dukungan komentar
(`catatan()`, `komentarDapatDieditOleh()`, dsb.) bila fitur komentar ikut dipasang.

---

## 4. Form Fields (`PemasukanForm`)

Section **Rincian Pemasukan** — bagian tanggal diganti:

| Field                 | Komponen     | Keterangan |
| --------------------- | ------------ | ---------- |
| `jenis_waktu`         | `Select`     | `options(EnumJenisWaktuPemasukan::class)`, `required()`, `default(SatuHari)`, `->live()`, `columnSpan(1)` |
| `tanggal_pelaksanaan` | `DatePicker` | `required()`. Label dinamis: `Tanggal Pelaksanaan` (1 hari) / `Tanggal Mulai` (rentang) |
| `tanggal_selesai`     | `DatePicker` | `visible()` + `required()` hanya bila rentang; `->afterOrEqual('tanggal_pelaksanaan')` dengan `validationMessages(['after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.'])` |

> Pola pembacaan state enum mengikuti closure `$sumberIs` yang sudah ada pada form ini
> (state bisa berupa instance enum maupun nilai mentah).
>
> `->afterStateUpdated()` pada `jenis_waktu`: mengosongkan `tanggal_selesai` saat
> kembali ke 1 hari, supaya nilai lama tidak ikut tersimpan.

Section baru **Status Pengajuan** (hanya pada halaman View/Edit, `hiddenOn('create')`):
menampilkan status berjalan + catatan revisi terakhir.

---

## 5. Table Columns (`PemasukansTable`)

| Kolom | Perubahan |
| ----- | --------- |
| `tanggal_pelaksanaan` | `->state(fn (Pemasukan $record) => $record->labelPeriode())`, tetap `sortable()` pada kolom aslinya. Label menjadi `Periode` |
| `jenis_waktu` | **Baru** — `badge()`, `toggleable(isToggledHiddenByDefault: true)` |
| `status` | **Baru** — `badge()`, `formatStateUsing(fn (Pemasukan $r) => $r->labelStatus())`, `color(fn (Pemasukan $r) => $r->status->getColor())` |

Filter baru: `SelectFilter::make('status')->options(EnumStatusPemasukan::class)`.

Record actions — ditambahkan **sebelum** `ActionGroup` yang sudah ada:

| Aksi | Visible saat |
| ---- | ------------ |
| `AjukanPemasukanAction` | `dapatDiajukan()` |
| `UnggahBuktiPemasukanAction` | status `MenungguBukti` |
| `MediaAction::make('lihatBukti')->path('bukti_path')` | `bukti_path` terisi |

`AuthorizedEditAction` dan `CaptchaDeleteAction` diberi `->visible(fn (Pemasukan $r) => $r->dapatDiubah())`.

---

## 6. Infolist Entries (`PemasukanInfolist`)

Signature diubah menjadi `configure(Schema $schema, ?string $resource = null)` — meniru
`RealisasiProgramKerjaInfolist` — agar bisa dipakai ulang oleh 3 Resource verifikasi.

Tambahan:

| Entry | Keterangan |
| ----- | ---------- |
| Stepper | `ViewEntry` ke `filament.infolists.pemasukan-stepper` |
| `jenis_waktu` | `TextEntry` badge |
| `tanggal_pelaksanaan` | diganti entry `periode` → `labelPeriode()`, label `Periode Pelaksanaan` |
| `status` | badge, `labelStatus()` |
| `catatan_verifikasi` | `->html()`, `placeholder('-')`, visible bila terisi |
| Section **Riwayat Verifikasi** | Rektor / Wakil Rektor / Biro Keuangan beserta waktunya |
| Section **Bukti Tanda Terima** | daftar berkas + tombol `MediaAction`, visible bila `bukti_path` terisi |

### Stepper Blade

`resources/views/filament/infolists/pemasukan-stepper.blade.php` — salinan pola
`realisasi-stepper.blade.php`: iterasi `EnumTahapanPemasukan::flowCases()`, tandai
tahap berjalan dari `tahapanStepper()`, tampilkan catatan revisi/penolakan
(`trim(strip_tags(...))`) pada tahap tempat keputusan itu dibuat.

---

## 7. Aksi Unit Kerja (`app/Filament/Actions/`)

### `AjukanPemasukanAction`

Meniru `AjukanRealisasiAction` (versi jauh lebih ringkas — tidak ada kuota/tunggakan).

- Label: `Ajukan` / `Perbaiki` (bila status `Revisi`).
- Schema: `CatatanRevisi`-style section berisi `catatan_verifikasi` bila ada.
- `->requiresConfirmation()` pada pengajuan pertama.
- Action: `status = statusTujuanPengajuan()`, `catatan_verifikasi = null`,
  `user_id = auth()->id()` bila masih kosong → `catatLog(...)` →
  `NotifikasiVerifikasi::pemasukanDiajukan()`.

> `CatatanRevisi::komponen()` saat ini bertipe `RealisasiProgramKerja`. Generalisasi
> jadi `komponen(Model $record)` yang membaca atribut `catatan_verifikasi`, sehingga
> dipakai bersama oleh realisasi dan pemasukan.

### `UnggahBuktiPemasukanAction`

- Label `Unggah Bukti Tanda Terima`, icon `heroicon-o-arrow-up-tray`, color `success`.
- Visible: `status === MenungguBukti`.
- Schema: `FileUpload::make('bukti_path')`
  - `->required()->multiple()->minFiles(1)->maxFiles(Setting::maksBuktiPemasukan())`
  - `->directory('bukti-pemasukan')`
  - `->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])`
  - `->maxSize(Setting::maksUkuranBuktiPemasukanKb())`
  - `->storeFileNamesIn('bukti_original_names')->downloadable()->openable()`
- Action: simpan berkas, `status = Valid`, `bukti_diserahkan_at = now()`,
  `divalidasi_at = now()` → `catatLog(Valid, ...)` → notifikasi ke verifikator.

Batas jumlah & ukuran berkas dibaca dari Pengaturan Sistem — lihat bagian 10.

---

## 8. Resource (`PemasukanResource`)

```php
public static function canEdit(Model $record): bool
{
    return $record->dapatDiubah() && static::currentUserCan(static::getPermissionName('update'));
}

public static function canDelete(Model $record): bool
{
    return $record->dapatDiubah() && static::currentUserCan(static::getPermissionName('delete'));
}
```

`getEloquentQuery()` menambah eager load `['pengaju', 'rektor', 'wakil', 'keuangan']`.

### Pengisian `user_id`

`user_id` **tidak** dijadikan field form — diisi otomatis dari sesi, di dua tempat:

```php
// CreatePemasukan
protected function mutateFormDataBeforeCreate(array $data): array
{
    return [...$data, 'user_id' => auth()->id()];
}
```

`AjukanPemasukanAction` juga mengisi `user_id` bila masih kosong, sebagai jaring
pengaman untuk record hasil impor/seeder yang tidak lewat halaman Create.

Pada infolist ditampilkan sebagai entry `pengaju.name` berlabel `Dicatat Oleh`.

Navigation badge tetap jumlah seluruh record, tetapi `getNavigationBadgeColor()`
menjadi `warning` bila ada pemasukan berstatus `Revisi` atau `MenungguBukti` milik
unit pengguna (yang menunggu tindakan unit kerja).

---

## 9. Export & Widget

### `PemasukansExport`

Headings menjadi:

```
unit_kerja, sumber, program_kerja, rincian_kegiatan, jenis_waktu,
tanggal_pelaksanaan, tanggal_selesai, nominal_pendapatan, status, keterangan
```

`tanggal_selesai` diisi `null` untuk jenis waktu 1 hari.

### `PemasukanOverview`

- `Total Pemasukan` → hanya status `Valid`, deskripsi diperjelas
  (`Hanya pemasukan yang sudah valid`).
- Stat baru **`Menunggu Verifikasi`** — jumlah pemasukan berstatus berjalan,
  warna `warning`, sehingga angka yang belum sah tetap terlihat tanpa ikut terhitung.

---

---

## 10. Pengaturan Sistem — batas berkas bukti (dinamis)

Batas jumlah dan ukuran berkas bukti **tidak di-hardcode**, melainkan mengikuti pola
setting yang sudah ada (`maks_proposal_realisasi`, `maks_ukuran_proposal_mb`).

### `App\Models\Setting`

Tambahkan dua konstanta beserta nilai bawaannya:

```php
/** Jumlah maksimum berkas bukti tanda terima pada satu pemasukan. */
public const MAKS_BUKTI_PEMASUKAN = 'maks_bukti_pemasukan';

/** Ukuran maksimum (MB) tiap berkas bukti tanda terima. */
public const MAKS_UKURAN_BUKTI_PEMASUKAN_MB = 'maks_ukuran_bukti_pemasukan_mb';

public const DEFAULTS = [
    // … yang sudah ada
    self::MAKS_BUKTI_PEMASUKAN => 3,
    self::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => 10,
];
```

Accessor bertipe, mengikuti gaya accessor yang sudah ada:

```php
public static function maksBuktiPemasukan(): int
{
    return max(1, (int) static::get(self::MAKS_BUKTI_PEMASUKAN));
}

/** Ukuran maksimum berkas bukti dalam kilobyte (dipakai FileUpload::maxSize). */
public static function maksUkuranBuktiPemasukanKb(): int
{
    return max(1, (int) static::get(self::MAKS_UKURAN_BUKTI_PEMASUKAN_MB)) * 1024;
}
```

> `SettingSeeder` **tidak perlu diubah** — seeder itu melakukan iterasi atas
> `Setting::DEFAULTS`, jadi entri baru otomatis ikut ter-seed.

### `App\Filament\Pages\PengaturanSistem`

Tiga tempat yang harus disentuh (kalau salah satu terlewat, nilainya tidak tersimpan
atau form-nya kosong saat dibuka):

1. **`mount()`** — tambahkan ke `$this->form->fill([...])`:

   ```php
   Setting::MAKS_BUKTI_PEMASUKAN => Setting::maksBuktiPemasukan(),
   Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => (int) Setting::get(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB),
   ```

2. **`form()`** — Section baru setelah `Dokumen Realisasi`:

   ```php
   Section::make('Bukti Tanda Terima Pemasukan')
       ->description('Membatasi berkas bukti tanda terima yang diunggah unit kerja untuk mengesahkan pemasukan.')
       ->icon(Heroicon::OutlinedBanknotes)
       ->columnSpanFull()
       ->columns(2)
       ->schema([
           TextInput::make(Setting::MAKS_BUKTI_PEMASUKAN)
               ->label('Maksimal Berkas Bukti')
               ->numeric()->required()->minValue(1)->maxValue(20)->suffix('berkas')
               ->helperText('Batas jumlah berkas bukti per pemasukan. Minimal unggahan tetap 1 berkas.'),
           TextInput::make(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB)
               ->label('Maksimal Ukuran Berkas Bukti')
               ->numeric()->required()->minValue(1)->maxValue(100)->suffix('MB')
               ->helperText('Batas ukuran tiap berkas bukti yang diunggah.'),
       ]),
   ```

3. **`save()`** — tambahkan dua baris `Setting::set(...)` dengan cast `(int)`.

Selain itu, `getSubheading()` diperbarui agar menyebut cakupan baru, dan
`$permissions['description']` tetap seperti semula (permission halaman tidak berubah).

### Test

`tests/Feature/PengaturanSistemTest.php` ditambahi kasus: mengubah
`maks_bukti_pemasukan` tersimpan dan langsung dipatuhi `UnggahBuktiPemasukanAction`
(mis. diisi 1 → unggah 2 berkas ditolak).

---

## Acceptance Criteria

- Jenis waktu `1 Hari` menyembunyikan dan mengosongkan `tanggal_selesai`.
- Jenis waktu `Rentang Waktu` mewajibkan `tanggal_selesai` dan menolak tanggal yang
  mendahului tanggal mulai, dengan pesan berbahasa Indonesia.
- Tabel dan detail menampilkan periode sebagai satu teks yang terbaca.
- Pemasukan baru berstatus `Draf`; tombol Ubah/Hapus hilang begitu pemasukan diajukan.
- Aksi `Ajukan` mengubah status ke `Diajukan`, mencatat riwayat, dan mengirim notifikasi.
- Pemasukan berstatus `Menunggu Bukti Tanda Terima` menampilkan aksi unggah bukti;
  setelah bukti diunggah statusnya menjadi `Valid` dan bukti dapat dipratinjau.
- Data pemasukan lama (sebelum migrasi) tetap muncul di Buku Anggaran.
- Setiap pemasukan menyimpan pencatatnya pada `user_id`, dan notifikasi
  revisi/penolakan/permintaan bukti hanya dikirim ke pengguna itu.
- Batas jumlah & ukuran berkas bukti dapat diubah dari halaman Pengaturan Sistem dan
  langsung berlaku pada aksi unggah bukti tanpa deploy ulang.

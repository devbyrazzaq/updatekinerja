## Resource: `Pemasukan` (Verifikasi Pemasukan) — 3 Resource tahap verifikasi

Tiga Resource baru atas model `Pemasukan`, satu per tahap, dalam grup navigasi baru
`Verifikasi Pemasukan`. Strukturnya **menyalin** grup `Verifikasi Realisasi` yang sudah
ada, sehingga trait bersama dipakai ulang tanpa perubahan.

### Informasi Dasar (berlaku untuk ketiganya)

| Properti          | Nilai                                                    |
| ----------------- | -------------------------------------------------------- |
| Model             | `App\Models\Pemasukan`                                   |
| Navigation Group  | `Verifikasi Pemasukan`                                   |
| Traits            | `HasResourceAuthorization`, `HasVerificationStageScopes` |
| `canCreate`       | `false`                                                  |
| `canEdit`         | `false`                                                  |
| `canDelete`       | `false`                                                  |
| Infolist          | `PemasukanInfolist::configure($schema, static::class)`   |
| Pages             | `index` (List), `view` (View)                            |

Grup ditambahkan ke `AppPanelProvider::navigationGroups()` **setelah** `'Pemasukan'`
dan **sebelum** `'Verifikasi Pengajuan'`.

---

## Pemetaan Tahap

| Resource | Direktori | Nav Label | Sort | `pendingStatuses()` | `stageActorColumn()` | `nextStageActorColumn()` | Status berikutnya |
| -------- | --------- | --------- | ---- | ------------------- | -------------------- | ------------------------ | ----------------- |
| `VerifikasiRektorPemasukanResource` | `VerifikasiRektorPemasukans/` | `Verifikasi Rektor` | 1 | `diajukan`, `verifikasi_rektor` | `rektor_id` | `wakil_id` | `VerifikasiWakil` |
| `VerifikasiWakilPemasukanResource` | `VerifikasiWakilPemasukans/` | `Verifikasi Wakil Rektor` | 2 | `verifikasi_wakil` | `wakil_id` | `keuangan_id` | `VerifikasiKeuangan` |
| `VerifikasiKeuanganPemasukanResource` | `VerifikasiKeuanganPemasukans/` | `Verifikasi Biro Keuangan` | 3 | `verifikasi_keuangan` | `keuangan_id` | `null` | `MenungguBukti` |

`rejectedStatusValue()` memakai default trait (`'ditolak'`) — sudah cocok.

Navigation icon: `Heroicon::OutlinedShieldCheck` (Rektor),
`Heroicon::OutlinedShieldExclamation` (Wakil), `Heroicon::OutlinedBanknotes` (Keuangan).

Navigation badge: `(string) self::pendingStageQuery()->count()`, warna `warning`.

---

## `getEloquentQuery()`

```php
return static::applyStageScope(
    parent::getEloquentQuery()->with([
        'unitKerja',
        'pengajuanProgramKerja.penawaranProgramKerja',
        'realisasiProgramKerja',
        'rektor', 'wakil', 'keuangan',
    ])
);
```

> Berbeda dengan verifikasi realisasi, **tidak** ada `KonteksProgramKerja::applyPelaksanaanVia()`
> — pemasukan bisa tidak tertaut program kerja sama sekali, sehingga menyaring lewat
> relasi penawaran justru akan membuang record yang sah. Penyempitan per periode
> ditangani filter tabel `tanggal_pelaksanaan`.
>
> Verifikator melihat seluruh unit kerja, jadi scope `permittedUnitIds()` milik
> `PemasukanResource` **tidak** diterapkan di sini.

---

## Permission

Tiap Resource mendefinisikan:

```php
public static function getPermissionDefinitions(): array
{
    $prefix = static::getPermissionPrefix();

    return [
        "view_any_{$prefix}" => 'Lihat Semua',
        "view_{$prefix}" => 'Lihat Detail',
        "verifikasi_{$prefix}" => 'Verifikasi Pemasukan (Rektor)', // disesuaikan per tahap
    ];
}

public static function currentUserCanVerify(): bool
{
    return static::currentUserCan(static::getPermissionName('verifikasi'));
}
```

Permission ter-scan otomatis oleh `PermissionRegistrar` dan muncul di form Role,
dikelompokkan pada grup sidebar `Verifikasi Pemasukan`.

---

## Table Columns

Satu kelas tabel per Resource (`Tables/VerifikasiRektorPemasukansTable.php`, dst.),
memakai `HasVerificationTableFilters`.

| Kolom | Tipe | Keterangan |
| ----- | ---- | ---------- |
| `rincian_kegiatan` | `TextColumn` | Label `Rincian Kegiatan`, `searchable()`, `wrap()` |
| `unitKerja.name` | `TextColumn` | Label `Unit Kerja`, `searchable()` |
| `sumber` | `TextColumn` | `badge()` |
| `periode` | `TextColumn` | `state(fn (Pemasukan $r) => $r->labelPeriode())`, label `Periode` |
| `nominal_pendapatan` | `TextColumn` | `money('IDR')`, `sortable()`, `summarize(Sum::make()->label('Total')->money('IDR'))` |
| `status` | `TextColumn` | `badge()`, `labelStatus()` + `status->getColor()` |
| `created_at` | `TextColumn` | Label `Dicatat`, `dateTime('d F Y H:i')`, `sortable()` |

### Filters

Semuanya dari `HasVerificationTableFilters` — **tanpa perlu mengubah trait**:

| Filter | Pemanggilan |
| ------ | ----------- |
| Unit kerja | `static::unitKerjaFilter()` — argumen relasi dibiarkan `null` karena `pemasukans` menyimpan `unit_kerja_id` langsung (trait sudah menangani kasus ini) |
| Waktu pengajuan | `static::waktuPengajuanFilter()` — default kolom `created_at` |
| Periode pelaksanaan | `static::waktuPengajuanFilter('tanggal_pelaksanaan', 'Periode Pelaksanaan')` |
| Jenis sumber | `SelectFilter::make('sumber')->options(EnumSumberPemasukan::class)` |

> **Tidak memakai `tahunKerjaFilter()`.** Filter itu menyaring lewat relasi pemilik
> `tahun_kerja_id`, sedangkan pemasukan boleh tidak tertaut pengajuan maupun realisasi
> sama sekali — record semacam itu akan selalu tersaring keluar. Penyempitan per
> periode ditangani filter `tanggal_pelaksanaan` di atas.

### Record Actions

```php
MediaAction::make('lihatBukti')
    ->label('Lihat Bukti')
    ->color('gray')
    ->path('bukti_path')
    ->visible(fn (Pemasukan $record): bool => filled($record->bukti_path)),
ActionGroup::make([
    ViewAction::make()->label('Detail'),
]),
```

---

## Pages

### `List…` (mis. `ListVerifikasiRektorPemasukans`)

- `use HasVerificationStageTabs;` → tab **Perlu Diverifikasi / Sudah Direspon / Ditolak**.
- Title: sesuai nav label.
- Subheading: `Pemasukan unit yang menunggu persetujuan Rektor.` (disesuaikan per tahap).
- Empty state: `Tidak ada pemasukan yang menunggu verifikasi Rektor.`

### `View…` (mis. `ViewVerifikasiRektorPemasukan`)

- Title: `Detail Pemasukan Unit`.
- Breadcrumbs: `[Resource::getUrl() => '<nav label>', '' => 'Detail']`.
- Header actions:

```php
protected function getHeaderActions(): array
{
    return [
        SetujuiPemasukanAction::make(),
        RevisiPemasukanAction::make(),
        TolakPemasukanAction::make(),
    ];
}
```

> Verifikasi realisasi memakai trait `HasSetujuiRealisasiAction` karena aksinya perlu
> dibungkus per halaman. Untuk pemasukan, aksi tidak menyimpan argumen per-item, jadi
> cukup dipanggil langsung. Bila nantinya aksi dipasang di tabel per baris, ikuti
> catatan memori proyek: **panggil aksi di dalam loop, jangan pakai `->arguments()`**.

---

## Actions (`app/Filament/Actions/`)

### `TahapVerifikasiPemasukanAction` (abstract)

Meniru `TahapVerifikasiRealisasiAction` — tahap disimpulkan dari **status record**,
bukan dari halaman pemanggil, agar aksi yang sama bisa dipasang di mana pun.

```php
protected static function tahap(Pemasukan $record): ?array
{
    return match ($record->status) {
        EnumStatusPemasukan::Diajukan,
        EnumStatusPemasukan::VerifikasiRektor => [
            'actor' => 'rektor_id',
            'timestamp' => 'disetujui_rektor_at',
            'nextStatus' => EnumStatusPemasukan::VerifikasiWakil,
            'resource' => VerifikasiRektorPemasukanResource::class,
        ],
        EnumStatusPemasukan::VerifikasiWakil => [
            'actor' => 'wakil_id',
            'timestamp' => 'disetujui_wakil_at',
            'nextStatus' => EnumStatusPemasukan::VerifikasiKeuangan,
            'resource' => VerifikasiWakilPemasukanResource::class,
        ],
        EnumStatusPemasukan::VerifikasiKeuangan => [
            'actor' => 'keuangan_id',
            'timestamp' => 'disetujui_keuangan_at',
            'nextStatus' => EnumStatusPemasukan::MenungguBukti,
            'resource' => VerifikasiKeuanganPemasukanResource::class,
        ],
        default => null,
    };
}

protected static function bolehMemutuskan(Pemasukan $record): bool
{
    $tahap = static::tahap($record);

    return $tahap !== null && $tahap['resource']::currentUserCanVerify();
}
```

### `SetujuiPemasukanAction`

- Label `Setujui`, icon `heroicon-o-check-badge`, color `success`.
- `->requiresConfirmation()`; schema hanya `RichEditor::make('catatan')` **opsional**
  (tidak ada penetapan nominal — keputusan #4).
- Action: `status = $tahap['nextStatus']`, isi kolom aktor + timestamp,
  `catatLog($tahap['nextStatus'], auth()->id(), $data['catatan'] ?? null)`.
- Bila tahap Biro Keuangan (`nextStatus === MenungguBukti`), kirim
  `NotifikasiVerifikasi::pemasukanMenungguBukti()` ke pengaju agar unit tahu harus
  mengunggah bukti tanda terima.

### `RevisiPemasukanAction`

- Label `Minta Revisi`, icon `heroicon-o-pencil-square`, color `warning`.
- `RichEditor::make('catatan')->label('Catatan Revisi')->required()`.
- Action: `status = Revisi`, `catatan_verifikasi = $data['catatan']`, isi aktor +
  timestamp → `catatLog(Revisi, …)` → `NotifikasiVerifikasi::pemasukanRevisi()`.

### `TolakPemasukanAction`

- Label `Tolak`, icon `heroicon-o-x-circle`, color `danger`, `->requiresConfirmation()`.
- `RichEditor::make('catatan')->label('Alasan Penolakan')->required()`.
- Action: `status = Ditolak` (final) → `catatLog(Ditolak, …)` →
  `NotifikasiVerifikasi::pemasukanDitolak()`.

Ketiganya `->visible(fn (Pemasukan $record) => static::bolehMemutuskan($record))`.

---

## Notifikasi

### `AturanPenerimaNotifikasi`

```php
public const PERAN_VERIFIKATOR_AWAL_PEMASUKAN = EnumRole::Rektor;

public function verifikatorPemasukan(): Collection;      // pemegang peran di atas
public function pengajuPemasukan(Pemasukan $pemasukan): Collection; // dari user_id
```

### `NotifikasiVerifikasi`

| Method | Penerima | Kapan |
| ------ | -------- | ----- |
| `pemasukanDiajukan(Pemasukan $record, bool $diajukanKembali)` | verifikator awal | unit mengajukan / mengajukan ulang |
| `pemasukanRevisi(Pemasukan $record, ?string $catatan)` | pengaju | verifikator minta revisi |
| `pemasukanDitolak(Pemasukan $record, ?string $catatan)` | pengaju | verifikator menolak |
| `pemasukanMenungguBukti(Pemasukan $record)` | pengaju | Biro Keuangan menyetujui |

Badan notifikasi memakai `Number::currency(..., 'IDR', 'id')` dan
`trim(strip_tags($catatan))` seperti method realisasi yang sudah ada. Tombol aksi
menuju `PemasukanResource::getUrl('view', …)` untuk pengaju, dan
`VerifikasiRektorPemasukanResource::getUrl('view', …)` untuk verifikator.

---

## Test (`tests/Feature/PemasukanVerifikasiTest.php`)

| Test | Yang diperiksa |
| ---- | -------------- |
| `test_pemasukan_baru_berstatus_draf` | default status + tombol Ubah masih ada |
| `test_unit_mengajukan_pemasukan` | Draf → Diajukan, log tercatat |
| `test_alur_tiga_tahap_verifikasi_berjalan` | Rektor → Wakil → Keuangan → `MenungguBukti`, kolom aktor & timestamp terisi |
| `test_unggah_bukti_membuat_pemasukan_valid` | `MenungguBukti` → `Valid`, `bukti_path` & `divalidasi_at` terisi |
| `test_revisi_kembali_ke_tahap_peminta_revisi` | revisi dari Wakil → diajukan ulang → langsung `VerifikasiWakil`, bukan Rektor |
| `test_penolakan_bersifat_final` | status `Ditolak`, aksi Ajukan tidak muncul |
| `test_pemasukan_tidak_valid_tidak_masuk_buku_anggaran` | saldo Buku Anggaran hanya menghitung status `Valid` |
| `test_pemasukan_tidak_dapat_diubah_setelah_diajukan` | `canEdit`/`canDelete` false |
| `test_verifikator_tanpa_permission_tidak_melihat_aksi` | `bolehMemutuskan()` |
| `test_setiap_tab_menampilkan_antrean_yang_benar` | scope pending/direspon/ditolak per tahap |

Test jenis waktu ditempatkan pada `tests/Feature/PemasukanTest.php` yang sudah ada:

- `test_rentang_waktu_wajib_mengisi_tanggal_selesai`
- `test_tanggal_selesai_tidak_boleh_mendahului_tanggal_mulai`
- `test_jenis_waktu_satu_hari_mengabaikan_tanggal_selesai`

> Test lama pada `PemasukanTest` dan `BukuAnggaranTest` yang membuat `Pemasukan`
> langsung perlu ditambahi `'status' => EnumStatusPemasukan::Valid`, jika tidak
> saldo yang diuji akan berubah.

---

## Acceptance Criteria

- Grup `Verifikasi Pemasukan` muncul di sidebar dengan 3 menu berbadge jumlah antrean.
- Tiap menu hanya menampilkan pemasukan pada tahapnya, terbagi dalam 3 tab.
- Aksi Setujui/Revisi/Tolak hanya tampil bagi pemegang permission tahap tersebut.
- Setujui meneruskan ke tahap berikutnya; tahap terakhir (Biro Keuangan) meneruskan
  ke `Menunggu Bukti Tanda Terima` dan memberi tahu unit kerja.
- Revisi mengembalikan ke unit dan, setelah diperbaiki, kembali ke tahap peminta revisi.
- Tolak bersifat final dan tidak dapat diajukan ulang.
- Seluruh keputusan tercatat di riwayat dengan aktor dan waktunya.

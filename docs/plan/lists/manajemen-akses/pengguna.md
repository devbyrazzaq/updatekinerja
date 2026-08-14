## Resource: `User` (Pengguna)

### Informasi Dasar

| Properti         | Nilai                     |
| ---------------- | ------------------------- |
| Model            | `App\Models\User`         |
| Navigation Group | `Manajemen Akses`         |
| Navigation Label | `Pengguna`                |
| Plural Label     | `Data Pengguna`           |
| Navigation Icon  | _(tentukan yang sesuai)_  |
| Role Filter      | Tidak ada                 |
| Shared Component | Tidak (resource-specific) |

> Mengikuti skill `filament-user-management` mode **DASAR** (tanpa relasi persona),
> login memakai `username`, password auto-generate `username + tanggal lahir (dd)`
> ditampilkan sekali via notifikasi. `username` **input manual**.

---

### Migration (add columns to `users`)

Tambah kolom pada tabel `users` (migration `add_profile_fields_to_users`):

| Kolom            | Tipe                                                              | Keterangan                    |
| ---------------- | ---------------------------------------------------------------- | ----------------------------- |
| `avatar_url`     | `string`, nullable                                               | Foto profil (disk privat)     |
| `username`       | `string`, `unique`, after `name`                                 | Identitas login               |
| `front_title`    | `string`, nullable                                               | Gelar depan                   |
| `back_title`     | `string`, nullable                                               | Gelar belakang                |
| `phone`          | `string`, nullable                                               | No. telepon                   |
| `birth_date`     | `date`, nullable                                                 | Tanggal lahir (untuk password default) |
| `gender`         | `enum('L','P')`, nullable                                        | Jenis kelamin (`EnumJenisKelamin`) |
| `unit_kerja_id`  | `foreignId`->`constrained('unit_kerjas')`->`nullOnDelete()`, nullable | Unit kerja penempatan   |
| `is_active`      | `boolean`, default `true`                                        | Status akun                   |

> `email` tetap ada tapi **nullable** (login pakai username). Route key = `username`.

---

### Model (User — tambahan)

- Fillable tambah: `avatar_url`, `front_title`, `back_title`, `username`, `phone`,
  `birth_date`, `gender`, `unit_kerja_id`, `is_active`
- Cast: `birth_date` → `date`, `gender` → `EnumJenisKelamin`, `is_active` → `boolean`
- RouteKeyName: `username`
- Implements `HasAvatar`: `getFilamentAvatarUrl()` (URL sementara disk privat) dengan
  fallback `getDefaultFilamentAvatarUrl()` (inisial via ui-avatars)
- Helper: `getFullName()` (gelar depan + nama + gelar belakang), `getAge()`
- Relationships:
    - `unitKerja()` → `belongsTo(UnitKerja::class)`
- Auth: konfigurasi login Filament memakai `username` (custom login / `->username()`),
  lihat skill `filament-user-management`.

---

### Form Fields

Section **Informasi Pribadi** (2 kolom):

| Field           | Komponen      | Keterangan                                                            |
| --------------- | ------------- | -------------------------------------------------------------------- |
| `avatar_url`   | `FileUpload`  | Avatar + image editor, JPG/JPEG maks 3MB, directory `avatar`, disk privat |
| `front_title`  | `TextInput`   | Nullable, dalam `FusedGroup` `Nama Lengkap Beserta Gelar`            |
| `name`         | `TextInput`   | Required, label `Nama Lengkap`, dalam `FusedGroup`                   |
| `back_title`   | `TextInput`   | Nullable, dalam `FusedGroup`                                         |
| `username`     | `TextInput`   | Required, unique, dikunci saat edit                                  |
| `email`        | `TextInput`   | Nullable, email, label `Email`                                       |
| `phone`        | `TextInput`   | Nullable, tel, prefix `+62`, label `Nomor Telepon`                   |
| `gender`       | `Select`      | Nullable, opsi `EnumJenisKelamin`, label `Jenis Kelamin`             |
| `birth_date`   | `DatePicker`  | Nullable, label `Tanggal Lahir`                                     |
| `is_active`    | `Checkbox`    | Label `Aktifkan Pengguna`, default `true`                           |

Section **Penempatan dan Hak Akses** (2 kolom):

| Field           | Komponen      | Keterangan                                                            |
| --------------- | ------------- | -------------------------------------------------------------------- |
| `unit_kerja_id`| `Select`      | Nullable, relationship `unitKerja` (`name`), searchable              |
| `roles`        | `Select`      | multiple, relationship `roles` (`name`), label `Role`, preload      |

> Password TIDAK di form create — auto-generate `username + dd(birth_date)` (fallback
> tanggal hari ini bila `birth_date` kosong), ditampilkan sekali via notifikasi setelah
> create. Edit: opsional field ubah password (`dehydrated` hanya bila diisi).

---

### Table Columns

| Kolom          | Tipe           | Keterangan                          |
| -------------- | -------------- | ----------------------------------- |
| `avatar_url`  | `ImageColumn`  | Circular 54px, fallback avatar inisial |
| `name`        | `TextColumn`   | Tampil `getFullName()`, searchable (`name`, `front_title`, `back_title`), sortable |
| `username`    | `TextColumn`   | Description = `phone`, searchable (`username`, `phone`) |
| `gender`      | `TextColumn`   | Badge, label `Jenis Kelamin`        |
| `roles.name`  | `TextColumn`   | Label `Role`, badge, `->badge()`    |
| `unitKerja.name` | `TextColumn` | Label `Unit Kerja`, toggleable      |
| `is_active`   | `ToggleColumn` | toggleable                          |
| `created_at`  | `TextColumn`   | Format tanggal, sortable, toggleable |

---

### Table Filters

| Filter          | Tipe            | Keterangan                       |
| --------------- | --------------- | -------------------------------- |
| `roles`         | `SelectFilter`  | Relationship `roles`, label `Role` |
| `unit_kerja_id` | `SelectFilter`  | Relationship `unitKerja`         |
| `gender`        | `SelectFilter`  | Opsi `EnumJenisKelamin`          |
| `is_active`     | `TernaryFilter` | Filter status aktif/nonaktif     |

---

### Infolist Entries

Layout `Grid(4)`: kartu avatar + nama lengkap (span 1) di kiri, section **Informasi Akun**
(span 3, 2 kolom) di kanan; di bawahnya section **Penempatan dan Hak Akses**.

| Entry            | Tipe         | Keterangan                              |
| ---------------- | ------------ | --------------------------------------- |
| `avatar_url`    | `ImageEntry` | Circular, fallback avatar inisial       |
| `fullname`      | `TextEntry`  | State `getFullName()`, hidden label     |
| `username`      | `TextEntry`  | Label "Username"                        |
| `email`         | `TextEntry`  | Label "Alamat Email"                    |
| `birth_date`    | `TextEntry`  | Label "Tanggal Lahir", date             |
| `age`           | `TextEntry`  | State `getAge()`, suffix " Tahun"       |
| `gender`        | `TextEntry`  | Label "Jenis Kelamin", badge            |
| `phone`         | `TextEntry`  | Label "Nomor Telepon"                   |
| `email_verified_at` | `TextEntry` | Label "Email Terverifikasi Pada"     |
| `is_active`     | `TextEntry`  | Badge Aktif/Tidak Aktif                 |
| `created_at`    | `TextEntry`  | Label "Data Dibuat Pada", dateTime      |
| `updated_at`    | `TextEntry`  | Label "Data Diperbarui Pada", dateTime  |
| `unitKerja.name`| `TextEntry`  | Label "Unit Kerja"                      |
| `roles.name`    | `TextEntry`  | Label "Role", badge                     |

---

### Halaman (Pages)

#### ListUsers
- Title: `Data Pengguna`
- Subheading: `Berikut adalah daftar Pengguna yang tersedia dalam sistem.`
- Header action: `Tambah Pengguna`

#### CreateUser
- Title: `Buat Pengguna Baru`
- Redirect setelah simpan: ke halaman View
- Notifikasi: `Pengguna berhasil dibuat` + notifikasi password default (persisten, sekali)

#### EditUser
- Notifikasi simpan: `Data Pengguna berhasil diperbarui`
- Redirect setelah simpan: ke halaman View

#### ViewUser
- Title: `Detail {name}`
- Breadcrumb: `Data Pengguna > Detail {name}`

---

### Acceptance Criteria

- Login memakai `username`, bukan email.
- Password default `username + dd(birth_date)` di-hash & ditampilkan sekali saat create.
- Admin dapat menetapkan satu/lebih role ke pengguna.

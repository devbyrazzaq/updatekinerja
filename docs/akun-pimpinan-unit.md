# Akun Pimpinan Unit (Data Seed)

Daftar akun hasil `Database\Seeders\PimpinanUnitSeeder` — satu akun pimpinan untuk
tiap unit kerja aktif. Tiap akun memegang role **Pimpinan Unit**, role pembatas data
unitnya sendiri (`Unit: {nama unit}`), dan role utama **Dosen** sehingga akunnya
terkelola lewat menu Dosen pada grup "Pengguna".

> **Kata sandi seluruh akun: `password`** (login memakai username, bukan email).
> Akun ini hanya untuk pengembangan/demo — jangan dipakai di produksi.

## Perintah

```bash
# Membuat / memperbarui akun (idempoten, aman diulang)
php artisan db:seed --class=PimpinanUnitSeeder

# Menghapus kembali akun-akun ini saja; role, unit kerja, dan akun lain tidak tersentuh
php artisan seed:hapus-pimpinan-unit          # dengan konfirmasi
php artisan seed:hapus-pimpinan-unit --force  # tanpa konfirmasi
```

Perintah hapus menghitung ulang username tiap unit lewat `PimpinanUnitSeeder::usernames()`,
lalu hanya menghapus akun yang usernamenya cocok, unit kerjanya sesuai, dan berrole
"Pimpinan Unit". Akun yang sudah terpakai sebagai relasi data (mis. pengaju program
kerja) dilewati dan dilaporkan, bukan dipaksa hapus, agar data lain tidak ikut rusak.

## Pola Username

8 digit angka yang diturunkan dari slug unit kerja (`crc32`), jadi unit yang sama
selalu menghasilkan username yang sama walau seeder diulang atau daftar unit
bertambah. Bila dua slug jatuh ke angka yang sama, dipakai angka bebas berikutnya.

## Daftar Akun (34)

| Username | Unit Kerja | Role Pembatas Data |
| --- | --- | --- |
| `57606008` | Rektorat | `Unit: Rektorat` |
| `98745903` | Wakil Rektor 1 | `Unit: Wakil Rektor 1` |
| `26327701` | Wakil Rektor 2 | `Unit: Wakil Rektor 2` |
| `77769347` | Wakil Rektor 3 | `Unit: Wakil Rektor 3` |
| `56744426` | Biro Administrasi Akademik dan Kemahasiswaan ( BAAK ) | `Unit: Biro Administrasi Akademik dan Kemahasiswaan ( BAAK )` |
| `51425376` | Biro Administrasi Keuangan ( BAK ) | `Unit: Biro Administrasi Keuangan ( BAK )` |
| `97045305` | Biro Administrasi Umum ( BAU ) | `Unit: Biro Administrasi Umum ( BAU )` |
| `41949637` | Biro Perencanaan Pembangunan dan Pemeliharaan Sarana Prasarana ( BP3S ) | `Unit: Biro Perencanaan Pembangunan dan Pemeliharaan Sarana Prasarana ( BP3S )` |
| `86247370` | Fakultas Ekonomi dan Bisnis ( FEB ) | `Unit: Fakultas Ekonomi dan Bisnis ( FEB )` |
| `38894005` | Fakultas Ilmu Kesehatan ( FIK ) | `Unit: Fakultas Ilmu Kesehatan ( FIK )` |
| `89859389` | Fakultas Sains, Teknologi dan Pendidikan ( FSTP ) | `Unit: Fakultas Sains, Teknologi dan Pendidikan ( FSTP )` |
| `18180439` | Halal Center | `Unit: Halal Center` |
| `52851496` | Kantor Urusan Internasional ( KUI ) | `Unit: Kantor Urusan Internasional ( KUI )` |
| `53899987` | Lembaga Pengembangan Al-Islam dan Kemuhammadiyahan ( LABAIK ) | `Unit: Lembaga Pengembangan Al-Islam dan Kemuhammadiyahan ( LABAIK )` |
| `91220800` | Lembaga Kemakmuran Masjid | `Unit: Lembaga Kemakmuran Masjid` |
| `43163959` | Lembaga Kemahasiswaan | `Unit: Lembaga Kemahasiswaan` |
| `37081018` | Lembaga Sertifikasi | `Unit: Lembaga Sertifikasi` |
| `31378816` | Lembaga Informasi dan Komunikasi ( LESIKOM ) | `Unit: Lembaga Informasi dan Komunikasi ( LESIKOM )` |
| `35943176` | Lembaga Penjaminan Mutu ( LPM ) | `Unit: Lembaga Penjaminan Mutu ( LPM )` |
| `51331334` | Lembaga Penelitian dan Pengabdian kepada Masyarakat ( LPPM ) | `Unit: Lembaga Penelitian dan Pengabdian kepada Masyarakat ( LPPM )` |
| `44367573` | Perpustakaan | `Unit: Perpustakaan` |
| `56504268` | Pusat Bahasa | `Unit: Pusat Bahasa` |
| `24569789` | Pusat Bisnis | `Unit: Pusat Bisnis` |
| `99678846` | Pusat Hak Kekayaan Intelektual dan Publikasi Ilmiah | `Unit: Pusat Hak Kekayaan Intelektual dan Publikasi Ilmiah` |
| `60560413` | Pusat Teknologi Informasi ( PTI ) | `Unit: Pusat Teknologi Informasi ( PTI )` |
| `20508063` | Pusat Laboratorium | `Unit: Pusat Laboratorium` |
| `63938835` | Pusat Layanan Bimbingan Konseling | `Unit: Pusat Layanan Bimbingan Konseling` |
| `60463526` | Pusat Pengembangan Jurnal | `Unit: Pusat Pengembangan Jurnal` |
| `49866715` | Pusat Pengembangan Pembelajaran dan RPL | `Unit: Pusat Pengembangan Pembelajaran dan RPL` |
| `19783096` | Pusat Tracer Study | `Unit: Pusat Tracer Study` |
| `30575874` | Sumber Daya Insan ( SDI ) | `Unit: Sumber Daya Insan ( SDI )` |
| `43915147` | Sekretariat Rektor | `Unit: Sekretariat Rektor` |
| `13574875` | Satuan Pengawas Internal ( SPI ) | `Unit: Satuan Pengawas Internal ( SPI )` |
| `43500869` | Biro Administrasi dan Keuangan ( BAK ) | `Unit: Biro Administrasi dan Keuangan ( BAK )` |

Email tiap akun mengikuti pola `{username}@umla.ac.id`, hanya sebagai pelengkap —
autentikasi tetap lewat username.

## Catatan

Akun seed lain di luar berkas ini (`superadmin`, `admin`, `rektor`, `wakilrektor`,
`keuangan`, `verifikator`, `unitteknik`, `pimpinanunit`) dibuat oleh `UserSeeder`
dan **tidak** ikut terhapus oleh `seed:hapus-pimpinan-unit`. Kata sandinya masih
mengikuti pola lama `{username}01` (kecuali `superadmin` yang memakai `password`).

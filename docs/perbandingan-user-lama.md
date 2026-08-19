# Perbandingan User Aplikasi Lama vs Database Sekarang

Dokumen ini membandingkan akun pengguna dua aplikasi pendahulu (`kinerja_2025` / LAMADU
dan `kinerja_2023`) dengan tabel `users` pada database `kinerja`, serta memetakan
dampaknya terhadap data yang sudah masuk.

Basis analisis:

- `kinerja.users` id **1–223** = kondisi sebelum impor (hasil seeder, dibuat 13 Agu 2026).
- `kinerja.users` id **224–251** = 28 akun yang **dibuat oleh importer** (14 Agu 2026).
- Aturan pencocokan di `App\Services\ImporDataLama\PencocokPengguna::cari()`:
  `username` → `email` → `name`, semuanya dibandingkan persis setelah `lower()` + rapikan spasi.

Ringkasan angka:

| Sumber | Jumlah user lama | Cocok ke akun lama | Akun baru dibuat |
| --- | ---: | ---: | ---: |
| `kinerja_2025` (LAMADU) | 37 | 31 | 6 |
| `kinerja_2023` | 35 | 13 | 22 |
| **Total** | **72** | **44** | **28** |

> **Penting soal dampak data.** Hanya `kinerja_2025` yang punya kolom `user_id`
> (`submission_submitted_bies`, `submission_logs`, `submission_status_quotes`,
> `realization_logs`, `realization_responses`, `realization_status_quotes`,
> `realization_submited_bies`, `budget_realization_approved_bies`).
> `kinerja_2023` **tidak punya kolom user sama sekali**, dan `ImporLama2023` memang
> menulis `'user_id' => null` di semua baris (lihat baris 502, 753, 762, 771, 781).
> Jadi seluruh user 2023 **nol dampak ke kepemilikan data** — mereka hanya menambah akun.

---

## A. User `kinerja_2025` (LAMADU) — inilah yang membawa data

Kolom **Ref lama** = jumlah baris di `kinerja_2025` yang menunjuk user tersebut.
Kolom **Pengaju** = jumlah `submission_submitted_bies` (pengajuan yang dia ajukan).
Kolom **Ref sekarang** = jumlah baris di `kinerja` yang kini menunjuk akun hasil pemetaan.

### A1. Cocok ke akun yang sudah ada (31 user)

| # lama | memberId | Nama di LAMADU | → Akun sekarang | Nama sekarang | Cocok via | Ref lama | Pengaju |
| ---: | --- | --- | ---: | --- | --- | ---: | ---: |
| 1 | 960326 | Super Admin | **#1** | Moch Fattahur Razzaq | email | 70 | 9 |
| 37 | 0721087801 | Arifal Aris | #70 | Arifal Aris | username | 0 | 0 |
| 39 | 0721046105 | Alifin | #58 | Alifin | username | 0 | 0 |
| 40 | 0706028601 | Abdul Majid | #44 | Abdul Majid | username | 30 | 3 |
| 41 | 0709077601 | Suryani Yuli Astuti | #207 | Suryani Yuli Astuti | username | 481 | 0 |
| 42 | 0726068303 | Lilis Maghfuroh | #140 | Lilis Maghfuroh | username | 0 | 0 |
| 43 | 0711077303 | Suyitno | #208 | Suyitno | username | 23 | 6 |
| 44 | 0712128301 | Virgianti Nur Farida | #217 | Virgianti Nur Farida**h** | username | 94 | 6 |
| 45 | 0717029104 | Mr. Eko Handoyo | #100 | Eko Handoyo | username | 111 | 6 |
| 46 | 0404089301 | Djati Wulan Kusumo | #96 | Djati Wulan Kusumo | username | 12 | 3 |
| 47 | 0720069401 | DIAS TIARA PUTRI UTOMO | #93 | Dias Tiara Putri Utomo | username | 113 | 12 |
| 48 | 0705119201 | TATAG SATRIA PRAJA | #211 | Tatag Satria Praja | username | 39 | 10 |
| 49 | 0714088505 | Dian Nurafifah | #91 | Dian Nurafifah | username | 65 | 8 |
| 50 | 0718019202 | Rofiatun Solekha | #194 | Rofiatun Solekha | username | 23 | 5 |
| 51 | 0715128501 | Sulistiyowati | #205 | Sulistiyowati | username | 70 | 13 |
| 52 | 0728027801 | Lilin Turlina | #139 | Lilin Turlina | username | 88 | 15 |
| 53 | 0720108801 | Abdul Rokhman | #46 | Abdul Rokhman | username | 98 | 20 |
| 54 | 0724087505 | Muhammad Ali Basyah | #160 | Muhammad Ali Basyah | username | 75 | 14 |
| 55 | 0723018301 | Amirul Amalia | #59 | Amirul Amalia | username | 64 | 6 |
| 56 | 0707059201 | Naajihah Mafruudloh | #167 | Naajihah Mafruudloh | email | 20 | 2 |
| 57 | 0724078501 | Ihda Mauliyah | #124 | Ihda Mauliyah | username | 1 | 0 |
| 59 | 0712058101 | M. Nurul Ihsan | #144 | M. Nurul Ihsan | **nama** | 78 | 12 |
| 60 | 0007067501 | Moh. Saifudin | #155 | Moh. Saifudin | username | 13 | 3 |
| 61 | 0731039601 | Masunatul Ubudiyah | #150 | Masunatul Ubudiyah | username | 63 | 6 |
| 62 | 0728059204 | Trijati Puspita Lestari | #215 | Trijati Puspita Lestari | username | 51 | 9 |
| 63 | 0713019101 | Primanitha Ria Utami | #182 | Primanitha Ria Utami | **nama** | 6 | 0 |
| 64 | 0715069701 | M. Cahyo Kriswantoro | #143 | M. Cahyo Kriswantoro | username | 0 | 0 |
| 65 | 0707068001 | Dadang Kusbiantoro | #82 | Dadang Kusbi**y**antoro | username | 25 | 2 |
| 68 | 0719107902 | ERNA NUR FAIZAH | #105 | Erna Nur Faizah | **nama** | 0 | 0 |
| 69 | 0718107902 | ERNA NUR FAIZAH | #105 | Erna Nur Faizah | username | 54 | 0 |
| 70 | 9990583878 | ARI SUSANDI | #69 | Ari Susandi | email | 158 | 19 |

Catatan: cocok "via **nama**" berarti username/NIDN-nya **berbeda** antara sistem lama dan
sekarang, jadi kecocokan hanya bertumpu pada nama — paling lemah dan paling perlu diverifikasi:

- #59 `0712058101` vs `users.username = 0712058**107**` (M. Nurul Ihsan)
- #63 `0713019101` vs `users.username = 0713019**105**` (Primanitha Ria Utami)
- #68 `0719107902` vs `users.username = 0718107902` (Erna Nur Faizah)

### A2. Tidak cocok → importer bikin akun baru (6 user)

| # lama | memberId | Nama di LAMADU | → Akun baru | Ref lama | Pengaju | Kenapa tidak cocok |
| ---: | --- | --- | ---: | ---: | ---: | --- |
| 5 | 0008127401 | A. Aziz Alimul Hidayat | **#225** | **568** | 0 | Rektor UMSurabaya, belum ada di seeder dosen |
| 38 | 0723096104 | Muhammad Bakri Priyodwi Admaja | **#226** | **287** | 0 | Seeder menyimpannya sebagai "Bakri Priyodwi At**maji**" NIDN `9900007399` |
| 58 | 0708119501 | Aditya Sindu Sakti | #227 | 0 | 0 | Belum ada di seeder |
| 66 | bp3sumla | Azhar Munif | #228 | 28 | 4 | Username unit, bukan NIDN |
| 67 | lembagamasjid | Sutikno | #229 | 63 | 3 | Username unit, bukan NIDN |
| 2 | 002 | Bayu Anugrah | #224 | 0 | 0 | Akun uji coba LAMADU |

---

## B. User `kinerja_2023` — hanya menambah akun, tidak memindahkan data

### B1. Cocok ke akun yang sudah ada (13 user)

| # lama | username | Nama di 2023 | → Akun sekarang | Nama sekarang | Cocok via |
| ---: | --- | --- | ---: | --- | --- |
| 1 | admin | People | #2 | Admin Sistem | username |
| 3 | birobak | Suryani Yuli Astuti, S.E, M.M | #207 | Suryani Yuli Astuti | email |
| 9 | birolesikom | Sulistiyowati, SST, M.Kes | #205 | Sulistiyowati | email |
| 10 | birolpm | Lilin Turlina | #139 | Lilin Turlina | email |
| 19 | pusatlbk | People | #237 | People | nama |
| 20 | pusatjurnal | Masunatul Ubudiyah, S.Kep., Ns., M.Kep | #150 | Masunatul Ubudiyah | email |
| 22 | tracerstudy | Trijati Puspita Lestari, S.Kep., Ns., M.Kep | #215 | Trijati Puspita Lestari | email |
| 27 | rektorat | Prof. A.Aziz Alimul Hidayat | #225 | A. Aziz Alimul Hidayat | email |
| 28 | wakilrektor | H.M Bakrie Priyo Dwi Atmaji, S.Kep., M.M | **#4** | **Wakil Rektor II** (akun dummy seeder) | username |
| 29 | adminpimpinan | People | #237 | People | nama |
| 32 | lembagamahasiswa | Dian Nurafifah, S.Si.T., M.Kes. | #91 | Dian Nurafifah | email |
| 33 | lembagamasjid | Sutikno,SH.MM | #229 | Sutikno | username |
| 35 | bp3sumla | Munif | #228 | Azhar Munif | username |

### B2. Tidak cocok → importer bikin akun baru (22 user)

Semua gagal cocok karena **nama masih memuat gelar** sementara `email` dan `username`-nya
berbeda dari akun seeder. Inilah sumber utama duplikasi orang.

| # lama | username | Nama di 2023 | → Akun baru | Sudah ada sebagai (akun lama) | Ref data akun lama |
| ---: | --- | --- | ---: | --- | ---: |
| 2 | birobaak | ABDUL MAJID S.E MM | #230 | #44 Abdul Majid | 30 |
| 4 | birobau | Lilis Maghfuroh S.Kep.,Ns.,M.Kes | #231 | #140 Lilis Maghfuroh | 0 |
| 5 | halalcenter | Djati Wulan Kusumo, S.Farm., M.Farm | #232 | #96 Djati Wulan Kusumo | 12 |
| 6 | birokui | Nur Hidayati, S.Kep., Ns., M.Kep | #233 | #176 Nur Hidayati | 0 |
| 7 | birolabaik | Tatag Satria Praja, S.Pd.I,M.Pd. | #234 | #211 Tatag Satria Praja | 39 |
| 8 | birolsp | Rofiatun Solekha, S.Pd., M.Sc. | #235 | #194 Rofiatun Solekha | 23 |
| 11 | birolppm | Abdul Rokhman, M.Kep. | #236 | #46 Abdul Rokhman | 98 |
| 13 | perpustakaan | Muhammad Ali Basyah, SH, MM | #238 | #160 Muhammad Ali Basyah | 75 |
| 15 | pusatbisnis | Ihda Mauliyah, SST.,M.Kes | #240 | #124 Ihda Mauliyah | 1 |
| 16 | pusathki | apt. Aditya Sindu Sakti., M.Si. | #241 | #227 Aditya Sindu Sakti (baru dari 2025) | 0 |
| 17 | pusatit | M. Nurul Ihsan, ST., M.Kom | #242 | #144 M. Nurul Ihsan | 78 |
| 18 | laboratorium | Dr. H. Dadang Kusbiantoro, S.Kep.Ns., M.Si | #243 | #82 Dadang Kusbiyantoro | 24 |
| 21 | pusatpembelajaran | Amirul Amalia., SSiT., M.Kes | #244 | #59 Amirul Amalia | 64 |
| 24 | sektor | apt.Primanitha Ria Utami.,M.Farm | #246 | #182 Primanitha Ria Utami | 6 |
| 26 | fikumla | Dr. Virgianti Nur Faridah, M.Kep | #248 | #217 Virgianti Nur Faridah | 94 |
| 30 | fstpumla | Eko Handoyo. S.Kom., M.Kom | #249 | #100 Eko Handoyo | 111 |
| 31 | febumla | Suyitno, S.E., M.M. | #250 | #208 Suyitno | 30 |
| 12 | medicalcenter | People | #237 | — (nama generik) | 0 |
| 14 | pusatbahasa | Pusat Bahas | #239 | — (nama unit, bukan orang) | 0 |
| 23 | birosdi | Lilis | #245 | — (nama tidak lengkap) | 0 |
| 25 | birospi | Dr. H. Masram, MM, M.Pd | #247 | — (belum ada padanan) | 0 |
| 34 | admin_rektor | Rektor Akses | #251 | — (akun akses rektor) | 0 |

---

## C. Yang berpengaruh ke data — urut dari dampak terbesar

Jumlah baris `kinerja` yang menunjuk akun tersebut, dihitung dari 16 kolom FK ke `users`
(`pengajuan_program_kerjas.user_id` & `verifikator_id`, `realisasi_program_kerjas.rektor_id`
/ `wakil_id` / `keuangan_id` / `penentu_nominal_id` / `verifikator_laporan_id`, log-log,
`pemasukans.*`, `jadwal_pencairans.keuangan_id`, `periodes.user_id`).

| Akun sekarang | Nama | Baris data | Asal | Status |
| ---: | --- | ---: | --- | --- |
| **#225** | A. Aziz Alimul Hidayat | **789** | 2025 #5 + 2023 #27 | Akun baru dari importer |
| **#207** | Suryani Yuli Astuti | **696** | 2025 #41 + 2023 #3 | Cocok ke akun seeder |
| **#226** | Muhammad Bakri Priyodwi Admaja | **371** | 2025 #38 | Akun baru dari importer |
| #69 | Ari Susandi | 157 | 2025 #70 | Cocok via email |
| #93 | Dias Tiara Putri Utomo | 113 | 2025 #47 | Cocok |
| #100 | Eko Handoyo | 111 | 2025 #45 | Cocok |
| #46 | Abdul Rokhman | 98 | 2025 #53 | Cocok |
| #217 | Virgianti Nur Faridah | 94 | 2025 #44 | Cocok |
| #139 | Lilin Turlina | 87 | 2025 #52 | Cocok |
| #144 | M. Nurul Ihsan | 78 | 2025 #59 | Cocok **via nama** |
| #160 | Muhammad Ali Basyah | 75 | 2025 #54 | Cocok |
| **#1** | Moch Fattahur Razzaq (`superadmin`) | **71** | 2025 #1 "Super Admin" | ⚠️ lihat D1 |
| #205 | Sulistiyowati | 69 | 2025 #51 | Cocok |
| #91 | Dian Nurafifah | 65 | 2025 #49 | Cocok |
| #59 | Amirul Amalia | 64 | 2025 #55 | Cocok |
| #150 | Masunatul Ubudiyah | 63 | 2025 #61 | Cocok |
| #229 | Sutikno | 58 | 2025 #67 | Akun baru dari importer |
| #105 | Erna Nur Faizah | 54 | 2025 #68 **+** #69 | ⚠️ lihat D3 |
| #215 | Trijati Puspita Lestari | 52 | 2025 #62 | Cocok |
| #211 | Tatag Satria Praja | 39 | 2025 #48 | Cocok |
| #44 | Abdul Majid | 30 | 2025 #40 | Cocok |
| #208 | Suyitno | 30 | 2025 #43 | Cocok |
| #228 | Azhar Munif | 28 | 2025 #66 | Akun baru dari importer |
| #82 | Dadang Kusbiyantoro | 24 | 2025 #65 | Cocok |
| #194 | Rofiatun Solekha | 23 | 2025 #50 | Cocok |
| #167 | Naajihah Mafruudloh | 21 | 2025 #56 | Cocok via email |
| #155 | Moh. Saifudin | 13 | 2025 #60 | Cocok |
| #96 | Djati Wulan Kusumo | 12 | 2025 #46 | Cocok |
| #182 | Primanitha Ria Utami | 6 | 2025 #63 | Cocok **via nama** |
| #124 | Ihda Mauliyah | 1 | 2025 #57 | Cocok |

**Total 30 akun memegang data.** Sisanya (221 akun) nol referensi data.

Dari 28 akun yang dibuat importer, hanya **4** yang memegang data (#225, #226, #228, #229).
**24 sisanya nol data**: #224, #227, dan seluruh #230–#251 (semuanya berasal dari `kinerja_2023`).

---

## D. Temuan yang perlu keputusan

### D1. Legacy "Super Admin" masuk ke akun developer

`kinerja_2025` user #1 bernama **"Super Admin"** dengan email `razzaqfattahur@gmail.com`.
Email itu identik dengan `users` #1 (`superadmin`, Moch Fattahur Razzaq), jadi cocok via email.
Akibatnya **71 baris data produksi — termasuk 9 pengajuan — kini tercatat atas nama akun
developer**. Perlu diputuskan: dibiarkan (memang akun operator yang sama) atau dipindah ke
akun fungsional (mis. #2 `admin`).

### D2. 17 orang punya 2 akun

Semua baris di tabel **B2** yang punya kolom "Sudah ada sebagai" terisi. Polanya konsisten:
akun 2025 cocok lewat NIDN (dan membawa seluruh data), akun 2023 gagal cocok karena nama
bergelar lalu dibuat akun kedua (nol data). Akun yang **dipertahankan harus yang lama**
(id kecil, pemegang data); username + email milik akun unit tinggal dipindahkan ke sana.

Kasus khusus **Aditya Sindu Sakti**: kedua akunnya baru (#227 dari 2025, #241 dari 2023),
dua-duanya nol data — bebas digabung ke mana saja.

### D3. Erna Nur Faizah: dua NIDN berbeda digabung jadi satu akun

`kinerja_2025` punya dua baris "ERNA NUR FAIZAH": #68 (`0719107902`, `abc@gmail.com`) dan
#69 (`0718107902`, `ab1@gmail.com`). Keduanya dipetakan ke `users` #105 — #69 cocok via
username, #68 cocok via nama. 54 baris data dari #69 kini di #105. **Perlu dikonfirmasi
apakah ini benar satu orang** (NIDN beda satu digit, kemungkinan salah ketik di LAMADU)
atau dua dosen berbeda yang datanya sekarang tercampur.

### D4. Wakil Rektor terpecah tiga

- `kinerja_2023` #28 `wakilrektor` (H.M Bakrie Priyo Dwi Atmaji) → cocok ke **#4 "Wakil Rektor II"**, akun dummy seeder.
- `kinerja_2025` #38 `0723096104` (Muhammad Bakri Priyodwi Admaja) → akun baru **#226**, memegang **371 baris data**.
- Seeder juga punya **#78 "Bakri Priyodwi Atmaji"** NIDN `9900007399`, nol data.

Tiga akun untuk satu orang. Yang harus dipertahankan: **#226**.

### D5. Rektor terpecah tiga

**#225** (A. Aziz Alimul Hidayat, 789 baris data) vs **#3** `rektor` "Rektor UMLA" (dummy
seeder, role Rektor) vs **#251** `admin-rektor` "Rektor Akses" (dari 2023, nol data).

### D6. Akun "People" menampung tiga identitas 2023

`medicalcenter`, `pusatlbk`, dan `adminpimpinan` semuanya bernama `People` (nilai default
kolom `name` di `kinerja_2023`). Ketiganya jatuh ke satu akun **#237**. Karena data 2023
tidak membawa `user_id`, tidak ada data yang tercampur — tapi #237 tidak mewakili siapa pun
dan sebaiknya dihapus.

### D7. Duplikat master data

`unit_kerjas` #6 `Biro Administrasi Keuangan ( BAK )` dan #34 `Biro Administrasi **dan**
Keuangan ( BAK )` adalah unit yang sama. Akibatnya ada dua role `Unit: …BAK…` dan dua akun
pimpinan seeder (#14 `51425376`, #42 `43500869`).

---

## E. Rekomendasi perbaikan pencocokan

Agar impor ulang tidak menghasilkan duplikat yang sama, `PencocokPengguna::kunci()` perlu
dinormalkan lebih jauh sebelum membandingkan **nama**:

1. Buang gelar depan/belakang (`Dr.`, `H.`, `Prof.`, `apt.`, `S.Kep.`, `M.Kes`, …) — ini
   menyelesaikan seluruh 17 kasus di D2 sekaligus.
2. Buang tanda baca (`.` `,`) dan rapatkan spasi, lalu bandingkan.
3. Tolak nama generik sebagai kunci pencocokan: `People`, `Super Admin`, `Admin`,
   `Rektor Akses` — jangan pernah dipakai untuk mencocokkan (D1, D6).
4. Nama saja jangan dijadikan kunci final tanpa konfirmasi; kasus D3 lolos justru karena
   nama identik padahal NIDN-nya beda.

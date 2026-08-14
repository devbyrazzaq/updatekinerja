# Sistem Manajemen Kinerja Universitas Muhammadiyah Lamongan

## Master Data

1. Periode -> id, name, slug, description, start_datetime, end_datetime, user_id, status ( boolean );
2. Tahun kerja -> id, name, slug, description, start_datetime, end_datetime, relasi ke model periode, belongsTo, status (boolean);
3. Kategori -> id, name, slug, code, description, status;
4. Bidang -> id, name, slug, code, description, status;
5. Unit Kerja -> id, name, slug, description, status;
6. Program -> id, name, slug, description, status;

## Anggaran
1. Pagu Anggaran -> pengaturan pagu anggaran berdasrkan Tahun Kerja Aktif. 
2. Rekening -> daftar rekening yang tersedia di dalam sistem.

## Pengaturan Program Kerja

1. Acuan Program Kerja -> id, name, aktifitas, indikator, nilai standar, satuan nilai standar, kode akun,  relasi ke Unit Kerja, Bidang, Kategori, Program -> kemudian ada relasi has many ke tahun kerja dan target.
2. Penawaran Program Kerja -> id, name, aktifitas, indikator, nilai standar, satuan nilai standar, kode akun, relasi ke unit, relasi ke bidang, relasi ke kategori, relasi ke program, relasi ke tahun kerja, dan kolom target. ( jadi ini hasil pemilihan antara Acuan Program kerja yang dipilih dan tahun kerjanya, )

## Pelaksanaan -> tampilan untuk unit kerja

1. Daftar Program Kerja -> tampilan seperti Penawaran Program Kerja 
2. Pengajuan Program Kerja -> id, relasi ke penawaran program kerja, kolom alokasi anggaran / pengajuan anggaran. deskripsi kegiatan.
3. Realisasi Program Kerja -> id, relasi ke pengajuan program kerja, name, description, start_datetime, end_datetime, anggaran digunakan ( tidak lebih dari alokasi anggaran pengajuan program studi ). status.

## Verifikasi Pengajuan
1. Tahap 1, -> menampilkan data Pengajuan Program Kerja, setiap pengajuan perlu persetujuan dari tahap ini, status dapat ditolak, revisi, dan diterima, ketika revisi user dapat mengedit kembali.

## Verifikasi Realisasi -> setiap realisasi perlu melalui tahap ini, 
1. Verifikasi Rektor -> bisa disetujui, ditolak, revisi, opsional memasukkan nominal disetujui
2. Verifikasi Wakil Rektor -> disetujui ditolak, revisi, wajib memasuka nominal disetujui apabila belum
3. Verifikasi Biro Keuangan -> menjadwalkan pencairan anggaran serta memberi info bahwa uang sudah diterima, dapat merubah status, dijadwalkan, atau menunggu laporan, syarat memberi status menunggu laporan harus menjadwalkan realisasi ini kapan di cairkan
4. Verifikasi Laporan -> jika laporan sudah diupload oleh user maka akan muncul disini, dan dapat disetujui, revisi, jika disetujui maka realisasi sudah selesai.

### Catatan pada Verifikasi Biro Keuangan ada tab jadwal Pencairan anggaran, jadi bisa membuat sekaliguse melihat jadwal pencairan kapan.


## Pengaturan Pengguna
- referensi ke data user pada sistem ../lamadu, ambil basic formnya jangan relasinya

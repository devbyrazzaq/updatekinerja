<?php

namespace Tests\Feature;

use App\Enums\EnumJenisLatarMasuk;
use App\Filament\Actions\PulihkanHalamanMasukAction;
use App\Filament\Clusters\PengaturanSistem\Pages\HalamanMasuk;
use App\Models\Setting;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tulisan dan latar halaman masuk: diatur di Pengaturan Sistem, dibaca kembali oleh
 * halaman masuk yang belum terautentikasi.
 */
class HalamanMasukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Setting::MASUK_LATAR_DISK);
    }

    public function test_halaman_masuk_menampilkan_tulisan_bawaan(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(Setting::DEFAULTS[Setting::MASUK_JUDUL])
            ->assertSee(Setting::DEFAULTS[Setting::MASUK_DESKRIPSI])
            ->assertSee('Catatan akses')
            ->assertSee('Sistem Manajemen Kinerja');
    }

    public function test_halaman_masuk_memakai_tulisan_yang_disimpan(): void
    {
        Setting::set(Setting::MASUK_JUDUL, 'Satu pintu pelaporan kinerja.');
        Setting::set(Setting::MASUK_DESKRIPSI, 'Masuk untuk melanjutkan program kerja.');
        Setting::set(Setting::MASUK_CATATAN_JUDUL, 'Sebelum masuk');
        Setting::set(Setting::MASUK_CATATAN, 'Akun dibuat oleh pengelola sistem.');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Satu pintu pelaporan kinerja.')
            ->assertSee('Masuk untuk melanjutkan program kerja.')
            ->assertSee('Sebelum masuk')
            ->assertSee('Akun dibuat oleh pengelola sistem.');
    }

    public function test_halaman_masuk_menyebut_jumlah_unit_kerja_dan_fakultas(): void
    {
        UnitKerja::create(['name' => 'Rektorat']);
        UnitKerja::create(['name' => 'Fakultas Ekonomi dan Bisnis ( FEB )']);
        UnitKerja::create(['name' => 'Fakultas Ilmu Kesehatan ( FIK )']);
        // Unit nonaktif tidak ikut dihitung, termasuk yang berupa fakultas.
        UnitKerja::create(['name' => 'Fakultas Sains, Teknologi dan Pendidikan ( FSTP )', 'is_active' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('3 Unit Kerja')
            ->assertSee('2 Fakultas');
    }

    public function test_pengaturan_terisi_tulisan_bawaan(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->assertFormSet([
                Setting::MASUK_JUDUL => Setting::DEFAULTS[Setting::MASUK_JUDUL],
                Setting::MASUK_DESKRIPSI => Setting::DEFAULTS[Setting::MASUK_DESKRIPSI],
                Setting::MASUK_LATAR_JENIS => EnumJenisLatarMasuk::Gambar,
            ]);
    }

    public function test_tulisan_dan_latar_youtube_dapat_disimpan(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->fillForm([
                Setting::MASUK_JUDUL => 'Satu pintu pelaporan kinerja.',
                Setting::MASUK_DESKRIPSI => 'Masuk untuk melanjutkan program kerja.',
                Setting::MASUK_LATAR_JENIS => EnumJenisLatarMasuk::Youtube->value,
                Setting::MASUK_LATAR_YOUTUBE => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Satu pintu pelaporan kinerja.', Setting::masukJudul());
        $this->assertSame('Masuk untuk melanjutkan program kerja.', Setting::masukDeskripsi());
        $this->assertSame(EnumJenisLatarMasuk::Youtube, Setting::masukLatarJenisTerpasang());
        $this->assertSame('dQw4w9WgXcQ', Setting::masukLatarYoutubeId());
    }

    public function test_catatan_akses_dapat_diubah_dan_dibatasi_jumlahnya(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->fillForm([
                Setting::MASUK_CATATAN_JUDUL => 'Sebelum masuk',
                Setting::MASUK_CATATAN => [
                    ['butir' => 'Akun dibuat oleh pengelola sistem.'],
                    ['butir' => 'Ganti password setelah masuk pertama kali.'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Sebelum masuk', Setting::masukCatatanJudul());
        $this->assertSame([
            'Akun dibuat oleh pengelola sistem.',
            'Ganti password setelah masuk pertama kali.',
        ], Setting::masukCatatan());
    }

    public function test_catatan_akses_menolak_butir_melebihi_batas(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->fillForm([
                Setting::MASUK_CATATAN => array_fill(0, Setting::MASUK_CATATAN_MAKS_BUTIR + 1, ['butir' => 'Butir catatan.']),
            ])
            ->call('save')
            ->assertHasFormErrors([Setting::MASUK_CATATAN]);
    }

    public function test_catatan_akses_boleh_dikosongkan(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->fillForm([Setting::MASUK_CATATAN => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], Setting::masukCatatan());
    }

    public function test_blok_catatan_tidak_dirender_saat_butirnya_kosong(): void
    {
        Setting::set(Setting::MASUK_CATATAN, '');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Catatan akses');
    }

    public function test_tautan_youtube_yang_tidak_dikenali_ditolak(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(HalamanMasuk::class)
            ->fillForm([
                Setting::MASUK_LATAR_JENIS => EnumJenisLatarMasuk::Youtube->value,
                Setting::MASUK_LATAR_YOUTUBE => 'https://vimeo.com/76979871',
            ])
            ->call('save')
            ->assertHasFormErrors([Setting::MASUK_LATAR_YOUTUBE]);
    }

    public function test_latar_turun_ke_gambar_saat_berkas_videonya_tidak_ada(): void
    {
        Setting::set(Setting::MASUK_LATAR_JENIS, EnumJenisLatarMasuk::Video->value);
        Setting::set(Setting::MASUK_LATAR_VIDEO, 'halaman-masuk/sudah-terhapus.mp4');

        $this->assertSame(EnumJenisLatarMasuk::Video, Setting::masukLatarJenis());
        $this->assertSame(EnumJenisLatarMasuk::Gambar, Setting::masukLatarJenisTerpasang());
        $this->assertStringContainsString(Setting::MASUK_LATAR_BAWAAN, Setting::masukLatarGambarUrl());
    }

    public function test_aksi_kembalikan_ke_bawaan_memulihkan_tampilan_halaman_masuk(): void
    {
        $this->actingAs(User::factory()->create());

        Storage::disk(Setting::MASUK_LATAR_DISK)->put('halaman-masuk/latar.webp', 'gambar');

        Setting::set(Setting::MASUK_JUDUL, 'Judul lama');
        Setting::set(Setting::MASUK_DESKRIPSI, 'Deskripsi lama');
        Setting::set(Setting::MASUK_CATATAN_JUDUL, 'Catatan lama');
        Setting::set(Setting::MASUK_CATATAN, 'Butir lama.');
        Setting::set(Setting::MASUK_LATAR_JENIS, EnumJenisLatarMasuk::Youtube->value);
        Setting::set(Setting::MASUK_LATAR_YOUTUBE, 'https://youtu.be/dQw4w9WgXcQ');
        Setting::set(Setting::MASUK_LATAR_GAMBAR, 'halaman-masuk/latar.webp');

        $this->jalankanAksiCaptcha(
            Livewire::test(HalamanMasuk::class),
            TestAction::make(PulihkanHalamanMasukAction::getDefaultName())->schemaComponent('aksiHalamanMasuk'),
        );

        $this->assertSame(Setting::DEFAULTS[Setting::MASUK_JUDUL], Setting::masukJudul());
        $this->assertSame(Setting::DEFAULTS[Setting::MASUK_DESKRIPSI], Setting::masukDeskripsi());
        $this->assertSame(EnumJenisLatarMasuk::Gambar, Setting::masukLatarJenis());
        $this->assertNull(Setting::masukLatarGambarPath());
        $this->assertNull(Setting::masukLatarYoutube());
        $this->assertSame(Setting::DEFAULTS[Setting::MASUK_CATATAN_JUDUL], Setting::masukCatatanJudul());
        $this->assertCount(2, Setting::masukCatatan());
        Storage::disk(Setting::MASUK_LATAR_DISK)->assertMissing('halaman-masuk/latar.webp');
    }

    public function test_pemulihan_ditolak_saat_jawaban_captcha_salah(): void
    {
        $this->actingAs(User::factory()->create());

        Setting::set(Setting::MASUK_JUDUL, 'Judul lama');

        $this->jalankanAksiCaptcha(
            Livewire::test(HalamanMasuk::class),
            TestAction::make(PulihkanHalamanMasukAction::getDefaultName())->schemaComponent('aksiHalamanMasuk'),
            jawabanBenar: false,
        );

        $this->assertSame('Judul lama', Setting::masukJudul());
    }
}

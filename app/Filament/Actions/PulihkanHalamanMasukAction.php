<?php

namespace App\Filament\Actions;

use App\Filament\Clusters\PengaturanSistem\Pages\HalamanMasuk;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * Mengembalikan tampilan halaman masuk ke bawaan sistem: gambar latar bawaan beserta
 * judul dan deskripsi aslinya. Berkas yang pernah diunggah ikut dibuang karena tidak
 * ada lagi yang merujuknya, jadi efeknya sama seperti menghapus — karena itu memakai
 * captcha, bukan sekadar konfirmasi.
 */
class PulihkanHalamanMasukAction extends AuthorizedAction
{
    use Concerns\HasArithmeticCaptcha;

    public static function getDefaultName(): ?string
    {
        return 'pulihkanHalamanMasuk';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kembalikan ke Bawaan');
        $this->icon(Heroicon::OutlinedArrowUturnLeft);
        $this->color('danger');
        // Ikut hak akses halamannya: yang boleh mengubah tampilan masuk boleh pula
        // mengembalikannya, tanpa permission tersendiri.
        $this->permission(fn (): bool => HalamanMasuk::canAccess());
        $this->modalHeading('Kembalikan Tampilan Halaman Masuk');
        $this->modalDescription('Judul, deskripsi, dan latar halaman masuk dikembalikan ke bawaan sistem.');
        $this->modalSubmitActionLabel('Ya, Kembalikan');
        $this->modalCancelActionLabel('Batal');
        $this->modalIcon(Heroicon::OutlinedArrowUturnLeft);
        $this->setUpArithmeticCaptcha();
        $this->schema(fn (): array => [
            Text::make($this->ringkasan()),
            ...$this->getCaptchaSchema(),
        ]);
        $this->action(function (array $data): void {
            if (! $this->captchaAnswerIsValid($data)) {
                $this->notifyWrongCaptchaAnswer();

                $this->halt();
            }

            Setting::pulihkanBawaanHalamanMasuk();

            $livewire = $this->getLivewire();

            // Isi form disegarkan supaya layar tidak lagi menampilkan nilai yang sudah
            // dibuang — tanpa ini menekan Simpan akan menuliskannya kembali.
            if (is_object($livewire) && method_exists($livewire, 'mount')) {
                $livewire->mount();
            }

            Notification::make()
                ->title('Tampilan halaman masuk dikembalikan')
                ->body('Halaman masuk kini memakai gambar, judul, dan deskripsi bawaan sistem.')
                ->success()
                ->send();
        });
    }

    /**
     * Menyebut apa saja yang akan hilang, karena berkas unggahan tidak bisa dipulihkan
     * dari dalam aplikasi.
     */
    protected function ringkasan(): string
    {
        $berkas = array_filter([Setting::masukLatarGambarPath(), Setting::masukLatarVideoPath()]);

        $ringkasan = 'Judul dan deskripsi kembali ke tulisan bawaan, dan latar kembali memakai gambar bawaan sistem.';

        if ($berkas !== []) {
            return $ringkasan.' Berkas yang pernah diunggah ('.implode(', ', $berkas).') ikut dihapus dan tidak bisa dipulihkan.';
        }

        return $ringkasan;
    }

    protected function getCaptchaLabel(): string
    {
        return 'Konfirmasi Pemulihan';
    }

    protected function getCaptchaHelperText(): string
    {
        return 'Ketik hasil operasi untuk mengembalikan tampilan halaman masuk ke bawaan.';
    }
}

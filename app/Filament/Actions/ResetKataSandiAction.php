<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * Mengembalikan kata sandi akun ke kata sandi awal ({@see User::kataSandiAwal()}),
 * mis. saat pemilik akun lupa kata sandinya. Kata sandi lama tidak bisa dipulihkan,
 * jadi memakai captcha, bukan sekadar konfirmasi. Permission-nya
 * `reset_password_<resource>`.
 *
 * Tidak tersedia untuk akun sendiri: kata sandi sendiri diganti lewat halaman profil.
 */
class ResetKataSandiAction extends AuthorizedAction
{
    use Concerns\HasArithmeticCaptcha;

    public static function getDefaultName(): ?string
    {
        return 'resetKataSandi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reset Kata Sandi');
        $this->icon(Heroicon::OutlinedKey);
        $this->color('danger');
        $this->permission(fn (Page $livewire, User $record): bool => $this->resource($livewire)::currentUserCanKelolaAkun('reset_password', $record));
        $this->hidden(fn (User $record): bool => $record->is(auth()->user()));

        $this->modalIcon(Heroicon::OutlinedKey);
        $this->modalHeading('Reset Kata Sandi');
        $this->modalSubmitActionLabel('Ya, Reset');
        $this->modalCancelActionLabel('Batal');
        $this->setUpArithmeticCaptcha();
        $this->schema(fn (User $record): array => [
            Text::make("Kata sandi {$record->getFullName()} dikembalikan ke kata sandi awal: username ditambah dua digit tanggal lahir. Kata sandi lama tidak berlaku lagi."),
            ...$this->getCaptchaSchema(),
        ]);

        $this->action(function (array $data, User $record): void {
            if (! $this->captchaAnswerIsValid($data)) {
                $this->notifyWrongCaptchaAnswer();

                $this->halt();
            }

            $kataSandi = $record->resetKataSandi();

            Notification::make()
                ->title('Kata sandi berhasil di-reset')
                ->body("Username: {$record->username} — Password: {$kataSandi}. Catat sekarang, kata sandi ini hanya ditampilkan sekali.")
                ->success()
                ->persistent()
                ->send();
        });
    }

    /**
     * @return class-string<UserResource>
     */
    protected function resource(Page $livewire): string
    {
        return $livewire::getResource();
    }

    protected function getCaptchaLabel(): string
    {
        return 'Konfirmasi Reset';
    }

    protected function getCaptchaHelperText(): string
    {
        return 'Ketik hasil operasi untuk me-reset kata sandi akun ini.';
    }
}

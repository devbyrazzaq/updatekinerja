<?php

namespace App\Filament\Actions\Concerns;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;

/**
 * Konfirmasi aritmatika untuk aksi yang tidak bisa dibatalkan (hapus, lepas, dan
 * sejenisnya). Modalnya memakai tampilan konfirmasi dasar: lebar sedang, teks dan
 * tombol rata tengah, sama seperti modal `requiresConfirmation()` bawaan Filament —
 * bedanya di sini ada satu isian yang harus dijawab benar sebelum aksi berjalan.
 *
 * Soal dan kuncinya disimpan di CACHE sisi server, bukan di state modal. Versi
 * sebelumnya menaruh kunci jawaban pada `Hidden::make('captcha_count')` lalu
 * membandingkannya dengan isian pengguna; di peramban jawaban yang benar selalu
 * ditolak, karena kunci itu ikut bolak-balik sebagai state Livewire dan bisa berubah
 * di perjalanan (kolom tersembunyi Filament pun dirender tanpa atribut `value`).
 * Gejalanya tidak pernah terlihat di test: Livewire::test tidak merender DOM modal
 * sama sekali, sehingga pembandingnya selalu utuh. Sekarang kunci jawaban tidak
 * pernah dikirim ke peramban, jadi tidak ada yang bisa merusaknya — sekaligus membuat
 * captcha-nya benar-benar tidak bisa dibaca dari halaman.
 *
 * Konsekuensinya aksi bercaptcha TIDAK boleh memakai `->fillForm()`: pengait itu
 * menimpa `mountUsing()` yang dipakai di sini untuk mengacak soal tiap modal dibuka.
 */
trait HasArithmeticCaptcha
{
    /**
     * Awalan kunci cache tempat soal aktif disimpan.
     */
    public const CAPTCHA_CACHE_PREFIX = 'filament.arithmetic_captcha.';

    /**
     * Umur soal — cukup panjang untuk mengisi modal, cukup pendek untuk tidak menumpuk.
     */
    public const CAPTCHA_TTL = 600;

    /**
     * Dipanggil di `setUp()` aksi: menyamakan tampilan modal dengan konfirmasi dasar
     * Filament sekaligus memasang pengacakan soal saat modal dibuka.
     */
    protected function setUpArithmeticCaptcha(): void
    {
        $this->useBasicConfirmationModal();

        // Soal diacak sekali per pembukaan modal, bukan per render: kalau diacak tiap
        // render, soal yang dibaca saat pemeriksaan jawaban bukan lagi soal yang tampil.
        $this->mountUsing(function (?Schema $schema): void {
            Cache::put($this->captchaCacheKey(), $this->generateCaptcha(), static::CAPTCHA_TTL);

            $schema?->fill();
        });
    }

    /**
     * Samakan tampilan modal dengan konfirmasi dasar Filament — modal berbasis schema
     * default-nya lebar dan rata kiri.
     */
    protected function useBasicConfirmationModal(): void
    {
        $this->modalWidth(Width::Medium);
        $this->modalAlignment(Alignment::Center);
        $this->modalFooterActionsAlignment(Alignment::Center);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function captchaAnswerIsValid(array $data): bool
    {
        $answer = $data['captcha_answer'] ?? null;

        return is_numeric($answer)
            && (int) $answer === $this->currentCaptcha()['answer'];
    }

    protected function notifyWrongCaptchaAnswer(): void
    {
        Notification::make()
            ->title('Gagal!')
            ->body('Jawaban salah. Silahkan coba lagi.')
            ->danger()
            ->send();
    }

    /**
     * @return array<int, TextInput>
     */
    protected function getCaptchaSchema(): array
    {
        return [
            TextInput::make('captcha_answer')
                ->label($this->getCaptchaLabel())
                ->required()
                ->numeric()
                // Dibaca ulang dari cache tiap render, jadi soal yang tampil selalu
                // soal yang sama dengan yang diperiksa `captchaAnswerIsValid()`.
                ->prefix(fn (): string => $this->currentCaptcha()['text'])
                ->helperText($this->getCaptchaHelperText()),
        ];
    }

    protected function getCaptchaLabel(): string
    {
        return 'Konfirmasi Tindakan';
    }

    protected function getCaptchaHelperText(): string
    {
        return 'Ketik hasil operasi untuk melanjutkan tindakan ini.';
    }

    /**
     * Kunci cache dipisah per pengguna dan per komponen Livewire supaya modal di dua
     * tab (atau dua komponen pada satu halaman) tidak saling menimpa soal.
     */
    public function captchaCacheKey(): string
    {
        $livewire = $this->getLivewire();

        $komponen = is_object($livewire) && method_exists($livewire, 'getId')
            ? $livewire->getId()
            : 'global';

        return static::CAPTCHA_CACHE_PREFIX.auth()->id().'.'.$komponen;
    }

    /**
     * Soal yang sedang berlaku. Dibuat saat modal dibuka; dibuat ulang di sini hanya
     * sebagai jaring pengaman bila entri cache-nya sudah kedaluwarsa.
     *
     * @return array{answer: int, text: string}
     */
    protected function currentCaptcha(): array
    {
        $captcha = Cache::get($this->captchaCacheKey());

        if (! is_array($captcha) || ! isset($captcha['answer'], $captcha['text'])) {
            $captcha = $this->generateCaptcha();

            Cache::put($this->captchaCacheKey(), $captcha, static::CAPTCHA_TTL);
        }

        return $captcha;
    }

    /**
     * Hasilnya selalu bilangan positif — pengurangan disusun dari angka yang lebih besar,
     * supaya pengguna tidak perlu mengetik tanda minus.
     *
     * @return array{answer: int, text: string}
     */
    protected function generateCaptcha(): array
    {
        $firstNumber = random_int(1, 20);
        $secondNumber = random_int(1, 20);
        $operator = ['+', '-'][random_int(0, 1)];

        if ($operator === '-' && $secondNumber > $firstNumber) {
            [$firstNumber, $secondNumber] = [$secondNumber, $firstNumber];
        }

        $answer = $operator === '+'
            ? $firstNumber + $secondNumber
            : $firstNumber - $secondNumber;

        return [
            'answer' => $answer,
            'text' => "{$firstNumber} {$operator} {$secondNumber} = ...",
        ];
    }
}

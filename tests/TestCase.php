<?php

namespace Tests;

use App\Filament\Actions\PulihkanHalamanMasukAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Jalankan aksi yang dilindungi captcha aritmatika.
     *
     * Kunci jawabannya ada di cache sisi server, bukan di state modal, jadi modalnya
     * harus dibuka lebih dulu agar soalnya terbit — `callAction()` sekali jalan tidak
     * bisa dipakai.
     *
     * @param  array<string, mixed>  $data  Isian modal selain jawaban captcha.
     */
    protected function jalankanAksiCaptcha(
        Testable $komponen,
        TestAction $aksi,
        array $data = [],
        bool $jawabanBenar = true,
    ): Testable {
        $komponen->mountAction($aksi);

        return $komponen
            ->setActionData([
                ...$data,
                'captcha_answer' => $this->jawabanCaptchaAktif($komponen) + ($jawabanBenar ? 0 : 1),
            ])
            ->callMountedAction();
    }

    /**
     * Kunci jawaban captcha yang sedang berlaku. Hanya berisi nilai setelah aksinya
     * di-mount, karena soalnya diacak ulang tiap modal dibuka.
     */
    protected function jawabanCaptchaAktif(Testable $komponen): int
    {
        return (int) $this->soalCaptchaAktif($komponen)['answer'];
    }

    /**
     * Soal captcha milik komponen Livewire tersebut, dibaca langsung dari cache —
     * kuncinya dirakit sama seperti di HasArithmeticCaptcha::captchaCacheKey().
     * Konstanta traitnya dibaca lewat salah satu kelas pemakainya karena konstanta
     * trait tidak bisa diakses langsung dari nama trait.
     *
     * @return array{answer: int, text: string}
     */
    protected function soalCaptchaAktif(Testable $komponen): array
    {
        $soal = Cache::get(PulihkanHalamanMasukAction::CAPTCHA_CACHE_PREFIX.auth()->id().'.'.$komponen->id());

        if (! is_array($soal)) {
            $this->fail('Belum ada soal captcha di cache — pastikan aksinya sudah di-mount lebih dulu.');
        }

        return $soal;
    }
}

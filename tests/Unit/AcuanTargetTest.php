<?php

namespace Tests\Unit;

use App\Models\AcuanTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nilai target acuan disimpan apa adanya sebagai teks, tetapi ditampilkan dengan
 * format desimal Indonesia agar nominal uang terbaca.
 */
class AcuanTargetTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function contohTarget(): array
    {
        return [
            'nominal uang' => ['15000000', 'rupiah', '15.000.000 rupiah'],
            'desimal' => ['1.79', 'persen', '1,79 persen'],
            'bilangan bulat kecil' => ['90', 'persen', '90 persen'],
            'awalan operator' => ['>40.1', 'persen', '>40,1 persen'],
            'ribuan dengan desimal' => ['2500000.5', 'rupiah', '2.500.000,5 rupiah'],
            'bukan angka' => ['Terakreditasi Unggul', null, 'Terakreditasi Unggul'],
        ];
    }

    public function test_format_nilai_teks_target_penawaran(): void
    {
        $this->assertSame('20.000.000 rupiah', AcuanTarget::formatNilai('20000000 rupiah'));
        $this->assertSame('0,02', AcuanTarget::formatNilai('0.02'));
        $this->assertSame('> 20 Juta', AcuanTarget::formatNilai('> 20 Juta'));
        $this->assertSame('0,4%', AcuanTarget::formatNilai('0,4%'));
    }

    #[DataProvider('contohTarget')]
    public function test_label_tampil_memformat_angka_desimal(string $nilai, ?string $satuan, string $harapan): void
    {
        $target = new AcuanTarget(['nilai' => $nilai, 'satuan' => $satuan]);

        $this->assertSame($harapan, $target->labelTampil());
        $this->assertSame(trim($nilai.' '.($satuan ?? '')), $target->label());
    }
}

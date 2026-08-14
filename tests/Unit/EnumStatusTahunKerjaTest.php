<?php

namespace Tests\Unit;

use App\Enums\EnumStatusTahunKerja;
use PHPUnit\Framework\TestCase;

class EnumStatusTahunKerjaTest extends TestCase
{
    public function test_fase_perencanaan_terbuka_untuk_tahun_berjalan_dan_perencanaan(): void
    {
        $this->assertTrue(EnumStatusTahunKerja::Perencanaan->bolehPerencanaan());
        $this->assertTrue(EnumStatusTahunKerja::Berjalan->bolehPerencanaan());
        $this->assertFalse(EnumStatusTahunKerja::Penutupan->bolehPerencanaan());
        $this->assertFalse(EnumStatusTahunKerja::Selesai->bolehPerencanaan());
    }

    public function test_fase_pelaksanaan_tidak_pernah_menyentuh_tahun_perencanaan(): void
    {
        $this->assertFalse(EnumStatusTahunKerja::Perencanaan->bolehPelaksanaan());
        $this->assertTrue(EnumStatusTahunKerja::Berjalan->bolehPelaksanaan());
        $this->assertTrue(EnumStatusTahunKerja::Penutupan->bolehPelaksanaan());
        $this->assertFalse(EnumStatusTahunKerja::Selesai->bolehPelaksanaan());
    }

    public function test_realisasi_baru_hanya_pada_tahun_berjalan(): void
    {
        $this->assertTrue(EnumStatusTahunKerja::Berjalan->bolehRealisasiBaru());

        foreach ([EnumStatusTahunKerja::Perencanaan, EnumStatusTahunKerja::Penutupan, EnumStatusTahunKerja::Selesai] as $status) {
            $this->assertFalse($status->bolehRealisasiBaru(), "{$status->value} seharusnya menolak realisasi baru.");
        }
    }

    public function test_hanya_berjalan_dan_perencanaan_yang_menempati_slot_tunggal(): void
    {
        $this->assertSame(
            [EnumStatusTahunKerja::Berjalan, EnumStatusTahunKerja::Perencanaan],
            EnumStatusTahunKerja::slotTunggal(),
        );

        $this->assertTrue(EnumStatusTahunKerja::Berjalan->adalahSlotTunggal());
        $this->assertTrue(EnumStatusTahunKerja::Perencanaan->adalahSlotTunggal());
        $this->assertFalse(EnumStatusTahunKerja::Penutupan->adalahSlotTunggal());
        $this->assertFalse(EnumStatusTahunKerja::Selesai->adalahSlotTunggal());
    }
}

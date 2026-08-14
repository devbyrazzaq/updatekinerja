<?php

namespace Database\Factories;

use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumUrgensiRealisasi;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealisasiProgramKerja>
 */
class RealisasiProgramKerjaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengajuan_program_kerja_id' => PengajuanProgramKerja::factory(),
            'name' => fake()->sentence(3),
            'anggaran_digunakan' => 0,
            'status' => EnumStatusRealisasi::Draft,
            'urgensi' => EnumUrgensiRealisasi::Rendah,
        ];
    }
}

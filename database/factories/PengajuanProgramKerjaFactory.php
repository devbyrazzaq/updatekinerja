<?php

namespace Database\Factories;

use App\Enums\EnumStatusPengajuan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengajuanProgramKerja>
 */
class PengajuanProgramKerjaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penawaran_program_kerja_id' => PenawaranProgramKerja::factory(),
            'unit_kerja_id' => UnitKerja::factory(),
            'user_id' => User::factory(),
            'alokasi_anggaran' => fake()->numberBetween(1_000_000, 50_000_000),
            'deskripsi_kegiatan' => fake()->sentence(),
            'status' => EnumStatusPengajuan::Draft,
        ];
    }
}

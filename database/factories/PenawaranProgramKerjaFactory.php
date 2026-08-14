<?php

namespace Database\Factories;

use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenawaranProgramKerja>
 */
class PenawaranProgramKerjaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun_kerja_id' => TahunKerja::factory(),
            'unit_kerja_id' => UnitKerja::factory(),
            'bidang_id' => Bidang::factory(),
            'kategori_id' => Kategori::factory(),
            'program_id' => Program::factory(),
            'name' => 'Penawaran '.fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Models\RealisasiDokumen;
use App\Models\RealisasiProgramKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealisasiDokumen>
 */
class RealisasiDokumenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(EnumJenisDokumenRealisasi::cases());

        return [
            'realisasi_program_kerja_id' => RealisasiProgramKerja::factory(),
            'type' => $type,
            'path' => $type->value.'-realisasi/'.fake()->uuid().'.pdf',
            'name' => fake()->words(3, true).'.pdf',
            'uploaded_at' => now(),
        ];
    }

    public function proposal(): static
    {
        return $this->state(fn (): array => ['type' => EnumJenisDokumenRealisasi::Proposal]);
    }

    public function laporan(): static
    {
        return $this->state(fn (): array => ['type' => EnumJenisDokumenRealisasi::Laporan]);
    }
}

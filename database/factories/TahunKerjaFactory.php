<?php

namespace Database\Factories;

use App\Enums\EnumStatusTahunKerja;
use App\Models\Periode;
use App\Models\TahunKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunKerja>
 */
class TahunKerjaFactory extends Factory
{
    protected $model = TahunKerja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'periode_id' => Periode::factory(),
            'name' => 'Tahun Kerja '.fake()->unique()->numerify('20##'),
            'tahun' => (int) $start->format('Y'),
            'description' => fake()->sentence(),
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+1 year'),
            'status' => EnumStatusTahunKerja::Selesai,
        ];
    }

    /**
     * Tahun kerja yang sedang dijalankan. Hanya boleh dipakai untuk satu record per
     * pengujian; model menolak slot ganda.
     */
    public function berjalan(): static
    {
        return $this->state(fn (): array => ['status' => EnumStatusTahunKerja::Berjalan]);
    }

    /**
     * Tahun kerja mendatang yang sedang direncanakan.
     */
    public function perencanaan(): static
    {
        return $this->state(fn (): array => ['status' => EnumStatusTahunKerja::Perencanaan]);
    }

    /**
     * Tahun kerja yang tinggal menuntaskan realisasi yang sudah berjalan.
     */
    public function penutupan(): static
    {
        return $this->state(fn (): array => ['status' => EnumStatusTahunKerja::Penutupan]);
    }
}

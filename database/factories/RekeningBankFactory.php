<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\RekeningBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RekeningBank>
 */
class RekeningBankFactory extends Factory
{
    protected $model = RekeningBank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'unit_kerja_id' => null,
            'nomor_rekening' => fake()->unique()->numerify('##########'),
            'atas_nama' => fake()->company(),
            'is_utama' => false,
            'is_active' => true,
        ];
    }

    /**
     * Rekening utama milik satu unit kerja, yang terpilih otomatis saat penjadwalan.
     */
    public function utama(): static
    {
        return $this->state(fn (): array => ['is_utama' => true]);
    }
}

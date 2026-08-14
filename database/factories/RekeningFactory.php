<?php

namespace Database\Factories;

use App\Models\Rekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rekening>
 */
class RekeningFactory extends Factory
{
    protected $model = Rekening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => '5.'.fake()->unique()->numerify('#.##.##'),
            'name' => 'Belanja '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}

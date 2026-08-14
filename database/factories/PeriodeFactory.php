<?php

namespace Database\Factories;

use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periode>
 */
class PeriodeFactory extends Factory
{
    protected $model = Periode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'name' => 'Periode '.fake()->unique()->year(),
            'description' => fake()->sentence(),
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+4 years'),
            'is_active' => false,
        ];
    }
}

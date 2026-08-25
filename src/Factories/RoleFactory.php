<?php

namespace Teksite\Authorize\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Teksite\Authorize\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => implode('-', $this->faker->words(2)),
            'description' => $this->faker->sentence(5),
            'hierarchy' => $this->faker->numberBetween(10, 90),
        ];
    }

}

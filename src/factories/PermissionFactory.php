<?php

namespace Teksite\Authorize\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Teksite\Authorize\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'=> implode('.', $this->faker->words(2)),
            'description' => fake()->unique()->sentence(5),
        ];
    }

}

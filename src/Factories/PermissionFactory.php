<?php

namespace Teksite\Authorize\Factories;

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
            'title'       =>  $this->faker->unique()->word() . '.' . $this->faker->randomElement(['read', 'create', 'update', 'delete']),
            'description' => $this->faker->sentence(),
        ];
    }

}

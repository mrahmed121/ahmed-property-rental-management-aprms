<?php

namespace Database\Factories;

use App\Domains\Shared\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Shared\Models\Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Estates';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+92-21-'.fake()->numerify('########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Karachi', 'Lahore', 'Islamabad']),
            'country' => 'Pakistan',
            'is_active' => true,
        ];
    }
}

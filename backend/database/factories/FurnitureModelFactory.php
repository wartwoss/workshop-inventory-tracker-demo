<?php

namespace Database\Factories;

use App\Models\FurnitureModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FurnitureModel>
 */
class FurnitureModelFactory extends Factory
{
    protected $model = FurnitureModel::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'model_asset_path' => null,
            'is_active' => true,
            'default_length_mm' => $this->faker->numberBetween(500, 1500),
            'min_length_mm' => $this->faker->numberBetween(300, 499),
            'max_length_mm' => $this->faker->numberBetween(1501, 2500),
            'default_width_mm' => $this->faker->numberBetween(400, 900),
            'min_width_mm' => $this->faker->numberBetween(200, 399),
            'max_width_mm' => $this->faker->numberBetween(901, 1500),
            'default_height_mm' => $this->faker->numberBetween(600, 900),
            'min_height_mm' => $this->faker->numberBetween(400, 599),
            'max_height_mm' => $this->faker->numberBetween(901, 1200),
        ];
    }
}

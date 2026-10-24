<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        $purpose = $this->faker->randomElement(Material::PURPOSES);

        return [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'material_group' => $this->faker->randomElement(['wood', 'ceramic', 'metal', 'upholstery']),
            'purpose' => $purpose,
            'stock_unit' => $this->faker->randomElement(Material::PURPOSE_UNITS[$purpose]),
            'quantity_on_hand' => '0.000000',
            'reorder_level' => null,
            'visual_key' => null,
            'color_hex' => null,
            'texture_path' => null,
            'is_active' => true,
        ];
    }
}

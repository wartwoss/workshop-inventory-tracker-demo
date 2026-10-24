<?php

namespace Database\Factories;

use App\Models\FurnitureModel;
use App\Models\ModelPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelPart>
 */
class ModelPartFactory extends Factory
{
    protected $model = ModelPart::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['board', 'leg', 'handle']);

        return [
            'furniture_model_id' => FurnitureModel::factory(),
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->words(2, true),
            'component_type' => $type,
            'material_group' => $this->faker->randomElement(['wood', 'metal', 'ceramic', 'upholstery']),
            'compatible_purposes' => [$type],
            'consumption_unit' => $type === 'board' ? 'm2' : 'ea',
            'formula_key' => $type === 'board' ? 'panel_area_m2' : 'fixed_each',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 0,
        ];
    }
}

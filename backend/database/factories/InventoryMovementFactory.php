<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'movement_type' => InventoryMovement::TYPE_MANUAL_ADJUSTMENT,
            'quantity_delta' => $this->faker->randomFloat(6, -10, 10),
            'balance_after' => $this->faker->randomFloat(6, 0, 100),
            'reason' => $this->faker->sentence(),
            'created_at' => now(),
        ];
    }
}

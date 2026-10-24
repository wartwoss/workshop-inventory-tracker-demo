<?php

namespace Tests\Feature;

use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_inventory_summary(): void
    {
        $wood = Material::factory()->create([
            'name' => 'Oak Board',
            'material_group' => 'wood',
            'stock_unit' => 'm2',
            'quantity_on_hand' => '10.000000',
            'reorder_level' => '12.000000',
            'is_active' => true,
        ]);

        Material::factory()->create([
            'name' => 'Brass Handle',
            'material_group' => 'metal',
            'stock_unit' => 'ea',
            'quantity_on_hand' => '0.000000',
            'reorder_level' => '5.000000',
            'is_active' => false,
        ]);

        $wood->inventoryMovements()->create([
            'movement_type' => 'manual_adjustment',
            'quantity_delta' => '2.000000',
            'balance_after' => '10.000000',
            'reason' => 'Stock count correction',
        ]);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('materials.total', 2)
            ->assertJsonPath('materials.active', 1)
            ->assertJsonPath('materials.inactive', 1)
            ->assertJsonPath('materials.low_stock', 2)
            ->assertJsonPath('materials.out_of_stock', 1)
            ->assertJsonPath('groups.0.material_group', 'metal')
            ->assertJsonPath('groups.0.materials_count', 1)
            ->assertJsonPath('recent_movements.0.material_id', $wood->id)
            ->assertJsonPath('recent_movements.0.movement_type', 'manual_adjustment')
            ->assertJsonPath('recent_movements.0.material_name', 'Oak Board')
            ->assertJsonPath('recent_movements.0.reason', 'Stock count correction');
    }
}

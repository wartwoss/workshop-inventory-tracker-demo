<?php

namespace Tests\Feature;

use App\Models\FurnitureModel;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureModelBuilderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_custom_furniture_model(): void
    {
        $board = Material::factory()->create([
            'name' => 'Oak board',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'is_active' => true,
        ]);

        $leg = Material::factory()->create([
            'name' => 'Steel leg',
            'purpose' => 'leg',
            'stock_unit' => 'ea',
            'material_group' => 'metal',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Console table',
            'components' => [
                [
                    'type' => 'board',
                    'label' => 'Top',
                    'material_id' => $board->id,
                    'length_mm' => 1200,
                    'width_mm' => 400,
                    'height_mm' => 20,
                    'pos_x_mm' => 0,
                    'pos_y_mm' => 700,
                    'pos_z_mm' => 0,
                    'rot_x_deg' => 0,
                    'rot_y_deg' => 0,
                    'rot_z_deg' => 0,
                    'shape' => 'rectangular',
                ],
                [
                    'type' => 'leg',
                    'label' => 'Leg',
                    'material_id' => $leg->id,
                    'length_mm' => 40,
                    'width_mm' => 40,
                    'height_mm' => 700,
                    'pos_x_mm' => 500,
                    'pos_y_mm' => 0,
                    'pos_z_mm' => 150,
                    'rot_x_deg' => 0,
                    'rot_y_deg' => 0,
                    'rot_z_deg' => 0,
                    'shape' => 'cylinder_leg',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/furniture-models', $payload);

        $response->assertCreated()
            ->assertJsonPath('slug', 'console-table')
            ->assertJsonPath('is_custom', true)
            ->assertJsonPath('name', 'Console table')
            ->assertJsonCount(2, 'model_parts');

        $this->assertDatabaseHas('furniture_models', [
            'slug' => 'console-table',
            'is_custom' => true,
        ]);
    }

    public function test_custom_model_quote_uses_builder_formulas(): void
    {
        $board = Material::factory()->create([
            'name' => 'Oak board',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        $leg = Material::factory()->create([
            'name' => 'Steel leg',
            'purpose' => 'leg',
            'stock_unit' => 'ea',
            'material_group' => 'metal',
            'quantity_on_hand' => '100.000000',
            'is_active' => true,
        ]);

        $model = FurnitureModel::factory()->create([
            'slug' => 'console-table',
            'name' => 'Console table',
            'is_custom' => true,
            'is_active' => true,
        ]);

        $model->modelParts()->create([
            'code' => 'board-1',
            'name' => 'Top',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'builder_board_area_m2',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 0,
            'material_id' => $board->id,
            'length_mm' => 1200,
            'width_mm' => 400,
            'height_mm' => 20,
            'label' => 'Top',
        ]);

        $model->modelParts()->create([
            'code' => 'leg-1',
            'name' => 'Leg',
            'component_type' => 'leg',
            'material_group' => 'metal',
            'compatible_purposes' => ['leg'],
            'consumption_unit' => 'ea',
            'formula_key' => 'builder_leg_each',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 1,
            'material_id' => $leg->id,
            'length_mm' => 40,
            'width_mm' => 40,
            'height_mm' => 700,
            'label' => 'Leg',
        ]);

        $payload = [
            'furniture_model_slug' => 'console-table',
            'length_mm' => 1000,
            'width_mm' => 500,
            'height_mm' => 750,
            'quantity' => 2,
            'parts' => [
                ['code' => 'board-1', 'material_id' => $board->id],
                ['code' => 'leg-1', 'material_id' => $leg->id],
            ],
        ];

        $response = $this->postJson('/api/v1/production/quote', $payload);

        $response->assertOk()
            ->assertJsonPath('is_sufficient', true);

        $components = collect($response->json('components'));
        $top = $components->firstWhere('part_code', 'board-1');
        $legComponent = $components->firstWhere('part_code', 'leg-1');

        $this->assertSame('m2', $top['unit']);
        $this->assertSame('0.960000', $top['total_quantity']); // 1200*400/1e6 * 2
        $this->assertSame('ea', $legComponent['unit']);
        $this->assertSame('2.000000', $legComponent['total_quantity']);
    }

    public function test_custom_model_quote_uses_order_specific_component_dimensions(): void
    {
        $board = Material::factory()->create([
            'name' => 'Oak board',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        $model = FurnitureModel::factory()->create([
            'slug' => 'editable-console',
            'name' => 'Editable console',
            'is_custom' => true,
            'is_active' => true,
        ]);

        $model->modelParts()->create([
            'code' => 'board-1',
            'name' => 'Top',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'builder_board_area_m2',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 0,
            'material_id' => $board->id,
            'length_mm' => 1200,
            'width_mm' => 600,
            'height_mm' => 18,
            'pos_x_mm' => 0,
            'pos_y_mm' => 700,
            'pos_z_mm' => 0,
            'shape' => 'rectangular',
            'label' => 'Top',
        ]);

        $response = $this->postJson('/api/v1/production/quote', [
            'furniture_model_slug' => 'editable-console',
            'length_mm' => 1000,
            'width_mm' => 500,
            'height_mm' => 1000,
            'quantity' => 1,
            'parts' => [[
                'code' => 'board-1',
                'material_id' => $board->id,
                'length_mm' => 2000,
                'width_mm' => 400,
                'height_mm' => 25,
                'pos_x_mm' => 30,
                'pos_y_mm' => 710,
                'pos_z_mm' => -20,
                'rot_x_deg' => 0,
                'rot_y_deg' => 15,
                'rot_z_deg' => 0,
                'shape' => 'rectangular',
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('configuration.parts.0.length_mm', 2000)
            ->assertJsonPath('configuration.parts.0.width_mm', 400)
            ->assertJsonPath('configuration.parts.0.height_mm', 25)
            ->assertJsonPath('configuration.parts.0.pos_x_mm', 30)
            ->assertJsonPath('components.0.per_unit_quantity', '0.800000');
    }

    public function test_custom_model_can_be_deleted_when_it_has_no_history(): void
    {
        $board = Material::factory()->create([
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
        ]);

        $response = $this->postJson('/api/v1/furniture-models', [
            'name' => 'Temporary model',
            'components' => [[
                'type' => 'board',
                'label' => 'Top',
                'material_id' => $board->id,
                'length_mm' => 1000,
                'width_mm' => 500,
                'height_mm' => 18,
                'shape' => 'rectangular',
            ]],
        ])->assertCreated();

        $slug = $response->json('slug');

        $this->deleteJson("/api/v1/furniture-models/{$slug}")
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('archived', false);

        $this->assertDatabaseMissing('furniture_models', ['slug' => $slug]);
    }

    public function test_custom_model_with_production_history_is_archived_not_deleted(): void
    {
        $model = FurnitureModel::factory()->create([
            'slug' => 'historical-model',
            'name' => 'Historical model',
            'is_custom' => true,
            'is_active' => true,
        ]);

        $model->productionOrders()->create([
            'length_mm' => 1000,
            'width_mm' => 500,
            'height_mm' => 700,
            'quantity' => 1,
            'configuration_snapshot' => ['furniture_model_slug' => 'historical-model'],
            'status' => 'confirmed',
            'idempotency_key' => 'historical-model-delete-test',
            'confirmed_at' => now(),
        ]);

        $this->deleteJson('/api/v1/furniture-models/historical-model')
            ->assertOk()
            ->assertJsonPath('deleted', false)
            ->assertJsonPath('archived', true);

        $this->assertDatabaseHas('furniture_models', [
            'slug' => 'historical-model',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('production_orders', 1);
    }

    public function test_creating_custom_model_rejects_inactive_materials(): void
    {
        $board = Material::factory()->create([
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/furniture-models', [
            'name' => 'Inactive board model',
            'components' => [[
                'type' => 'board',
                'label' => 'Top',
                'material_id' => $board->id,
                'length_mm' => 1000,
                'width_mm' => 500,
                'height_mm' => 18,
                'shape' => 'rectangular',
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['components.0.material_id']);
    }

    public function test_creating_custom_model_rejects_mismatched_material_purpose(): void
    {
        $handle = Material::factory()->create([
            'name' => 'Brass handle',
            'purpose' => 'handle',
            'stock_unit' => 'ea',
            'material_group' => 'metal',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Bad model',
            'components' => [
                [
                    'type' => 'leg',
                    'label' => 'Leg',
                    'material_id' => $handle->id,
                    'length_mm' => 40,
                    'width_mm' => 40,
                    'height_mm' => 700,
                    'shape' => 'rectangular_leg',
                ],
            ],
        ];

        $this->postJson('/api/v1/furniture-models', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['components.0.material_id']);
    }

    public function test_circular_and_rectangular_board_quotes_use_area_in_m2(): void
    {
        $circularBoard = Material::factory()->create([
            'name' => 'Oak circular board',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        $rectangularBoard = Material::factory()->create([
            'name' => 'Birch rectangular board',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        $model = FurnitureModel::factory()->create([
            'slug' => 'round-table',
            'name' => 'Round table',
            'is_custom' => true,
            'is_active' => true,
            'min_length_mm' => 100,
            'max_length_mm' => 2000,
            'min_width_mm' => 100,
            'max_width_mm' => 2000,
            'min_height_mm' => 100,
            'max_height_mm' => 2000,
        ]);

        $model->modelParts()->create([
            'code' => 'board-1',
            'name' => 'Top',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'builder_board_area_m2',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 0,
            'material_id' => $circularBoard->id,
            'length_mm' => 1000,
            'width_mm' => 1000,
            'height_mm' => 20,
            'shape' => 'circular',
            'label' => 'Top',
        ]);

        $model->modelParts()->create([
            'code' => 'board-2',
            'name' => 'Shelf',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'builder_board_area_m2',
            'formula_parameters' => [],
            'is_configurable' => true,
            'sort_order' => 1,
            'material_id' => $rectangularBoard->id,
            'length_mm' => 1000,
            'width_mm' => 600,
            'height_mm' => 20,
            'shape' => 'rectangular',
            'label' => 'Shelf',
        ]);

        $payload = [
            'furniture_model_slug' => 'round-table',
            'length_mm' => 800,
            'width_mm' => 800,
            'height_mm' => 700,
            'quantity' => 1,
            'parts' => [
                ['code' => 'board-1', 'material_id' => $circularBoard->id],
                ['code' => 'board-2', 'material_id' => $rectangularBoard->id],
            ],
        ];

        $response = $this->postJson('/api/v1/production/quote', $payload);
        $response->assertOk()->assertJsonPath('is_sufficient', true);

        $components = collect($response->json('components'));
        $circularPart = $components->firstWhere('part_code', 'board-1');
        $rectangularPart = $components->firstWhere('part_code', 'board-2');

        // π * (500 mm)^2 / 1e6 = 0.785398... m²
        $this->assertSame('m2', $circularPart['unit']);
        $this->assertStringStartsWith('0.7853', $circularPart['total_quantity']);

        // 1000 mm * 600 mm / 1e6 = 0.6 m²
        $this->assertSame('m2', $rectangularPart['unit']);
        $this->assertStringStartsWith('0.6000', $rectangularPart['total_quantity']);
    }

    public function test_leg_and_handle_shape_does_not_change_piece_consumption(): void
    {
        $leg = Material::factory()->create([
            'name' => 'Steel leg',
            'purpose' => 'leg',
            'stock_unit' => 'ea',
            'material_group' => 'metal',
            'quantity_on_hand' => '100.000000',
            'is_active' => true,
        ]);

        $handle = Material::factory()->create([
            'name' => 'Brass handle',
            'purpose' => 'handle',
            'stock_unit' => 'ea',
            'material_group' => 'metal',
            'quantity_on_hand' => '100.000000',
            'is_active' => true,
        ]);

        $model = FurnitureModel::factory()->create([
            'slug' => 'shape-test',
            'name' => 'Shape test',
            'is_custom' => true,
            'is_active' => true,
            'min_length_mm' => 100,
            'max_length_mm' => 2000,
            'min_width_mm' => 100,
            'max_width_mm' => 2000,
            'min_height_mm' => 100,
            'max_height_mm' => 2000,
        ]);

        $shapes = [
            ['type' => 'leg', 'shape' => 'cube_foot', 'material_id' => $leg->id],
            ['type' => 'leg', 'shape' => 'angular_foot', 'material_id' => $leg->id],
            ['type' => 'leg', 'shape' => 'round_foot', 'material_id' => $leg->id],
            ['type' => 'leg', 'shape' => 'rectangular_leg', 'material_id' => $leg->id],
            ['type' => 'leg', 'shape' => 'cylinder_leg', 'material_id' => $leg->id],
            ['type' => 'handle', 'shape' => 'knob', 'material_id' => $handle->id],
            ['type' => 'handle', 'shape' => 'bulb_knob', 'material_id' => $handle->id],
            ['type' => 'handle', 'shape' => 'bar', 'material_id' => $handle->id],
        ];

        foreach ($shapes as $index => $definition) {
            $model->modelParts()->create([
                'code' => "part-{$index}",
                'name' => "Part {$index}",
                'component_type' => $definition['type'],
                'material_group' => 'metal',
                'compatible_purposes' => [$definition['type']],
                'consumption_unit' => 'ea',
                'formula_key' => $definition['type'] === 'leg' ? 'builder_leg_each' : 'builder_handle_each',
                'formula_parameters' => [],
                'is_configurable' => true,
                'sort_order' => $index,
                'material_id' => $definition['material_id'],
                'length_mm' => 50,
                'width_mm' => 50,
                'height_mm' => 100,
                'shape' => $definition['shape'],
                'label' => "Part {$index}",
            ]);
        }

        $parts = $model->modelParts()->pluck('code')->map(fn ($code, $index) => [
            'code' => $code,
            'material_id' => $shapes[$index]['material_id'],
        ])->values()->all();

        $payload = [
            'furniture_model_slug' => 'shape-test',
            'length_mm' => 500,
            'width_mm' => 500,
            'height_mm' => 500,
            'quantity' => 3,
            'parts' => $parts,
        ];

        $response = $this->postJson('/api/v1/production/quote', $payload);
        $response->assertOk()->assertJsonPath('is_sufficient', true);

        foreach ($response->json('components') as $component) {
            $this->assertSame('ea', $component['unit']);
            $this->assertSame('3.000000', $component['total_quantity']);
        }
    }

    public function test_other_material_cannot_be_used_in_custom_model(): void
    {
        $other = Material::factory()->create([
            'name' => 'Glue',
            'purpose' => 'other',
            'stock_unit' => 'L',
            'material_group' => 'finish',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Bad model',
            'components' => [
                [
                    'type' => 'board',
                    'label' => 'Top',
                    'material_id' => $other->id,
                    'length_mm' => 1000,
                    'width_mm' => 600,
                    'height_mm' => 20,
                    'shape' => 'rectangular',
                ],
            ],
        ];

        $this->postJson('/api/v1/furniture-models', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['components.0.material_id']);
    }
}

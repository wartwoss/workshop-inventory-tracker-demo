<?php

namespace Tests\Feature;

use App\Models\Material;
use Database\Seeders\FurnitureModelSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ModelPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderConfirmApiTest extends TestCase
{
    use RefreshDatabase;

    private const IDEMPOTENCY_KEY = 'test-key-123';

    private function seedCatalogue(): void
    {
        $this->seed([
            MaterialSeeder::class,
            FurnitureModelSeeder::class,
            ModelPartSeeder::class,
        ]);
    }

    private function tablePayload(int $quantity = 1): array
    {
        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        return [
            'furniture_model_slug' => 'table',
            'length_mm' => 1200,
            'width_mm' => 700,
            'height_mm' => 740,
            'quantity' => $quantity,
            'parts' => [
                ['code' => 'top', 'material_id' => $oak->id],
                ['code' => 'legs', 'material_id' => $pine->id],
            ],
        ];
    }

    private function bedsidePayload(int $quantity = 1): array
    {
        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $birch = Material::where('sku', 'BIR-040')->firstOrFail();
        $foot = Material::where('sku', 'PIN-FOOT')->firstOrFail();
        $brass = Material::where('sku', 'BRA-HDL')->firstOrFail();

        return [
            'furniture_model_slug' => 'bedside-cabinet',
            'length_mm' => 450,
            'width_mm' => 400,
            'height_mm' => 650,
            'quantity' => $quantity,
            'parts' => [
                ['code' => 'carcass', 'material_id' => $birch->id],
                ['code' => 'door', 'material_id' => $oak->id],
                ['code' => 'handle', 'material_id' => $brass->id],
                ['code' => 'feet', 'material_id' => $foot->id],
            ],
        ];
    }

    public function test_successful_confirmation_creates_order_and_deducts_stock(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $response = $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('furniture_model.slug', 'table')
            ->assertJsonCount(2, 'production_consumptions')
            ->assertJsonCount(2, 'inventory_movements');

        $orderId = $response->json('id');

        $this->assertDatabaseHas('production_orders', [
            'id' => $orderId,
            'status' => 'confirmed',
            'idempotency_key' => self::IDEMPOTENCY_KEY,
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '11.534000',
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '46.000000',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'production_order_id' => $orderId,
            'material_id' => $oak->id,
            'movement_type' => 'production',
            'quantity_delta' => '-0.966000',
            'balance_after' => '11.534000',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'production_order_id' => $orderId,
            'material_id' => $pine->id,
            'movement_type' => 'production',
            'quantity_delta' => '-4.000000',
            'balance_after' => '46.000000',
        ]);
    }

    public function test_custom_order_confirmation_uses_configured_component_dimensions(): void
    {
        $board = Material::factory()->create([
            'name' => 'Custom oak',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'material_group' => 'wood',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        $model = \App\Models\FurnitureModel::factory()->create([
            'slug' => 'custom-order-dimensions',
            'name' => 'Custom order dimensions',
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
            'length_mm' => 1000,
            'width_mm' => 500,
            'height_mm' => 18,
            'pos_x_mm' => 0,
            'pos_y_mm' => 700,
            'pos_z_mm' => 0,
            'shape' => 'rectangular',
            'label' => 'Top',
        ]);

        $payload = [
            'furniture_model_slug' => 'custom-order-dimensions',
            'length_mm' => 1000,
            'width_mm' => 500,
            'height_mm' => 1000,
            'quantity' => 2,
            'parts' => [[
                'code' => 'board-1',
                'material_id' => $board->id,
                'length_mm' => 2000,
                'width_mm' => 450,
                'height_mm' => 25,
                'pos_x_mm' => 25,
                'pos_y_mm' => 720,
                'pos_z_mm' => 0,
                'rot_x_deg' => 0,
                'rot_y_deg' => 0,
                'rot_z_deg' => 0,
                'shape' => 'rectangular',
            ]],
        ];

        $response = $this->postJson('/api/v1/production-orders/confirm', $payload, [
            'Idempotency-Key' => 'custom-dimension-order-test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('configuration.parts.0.length_mm', 2000)
            ->assertJsonPath('configuration.parts.0.width_mm', 450)
            ->assertJsonPath('production_consumptions.0.quantity_consumed', '1.800000');

        $this->assertDatabaseHas('materials', [
            'id' => $board->id,
            'quantity_on_hand' => '8.200000',
        ]);
    }

    public function test_confirmation_is_idempotent(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $first = $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ]);
        $first->assertCreated();

        $second = $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ]);
        $second->assertOk()
            ->assertJsonPath('id', $first->json('id'));

        $this->assertDatabaseCount('production_orders', 1);
        $this->assertDatabaseCount('production_consumptions', 2);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '11.534000',
        ]);
        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '46.000000',
        ]);
    }

    public function test_reusing_idempotency_key_with_different_payload_returns_409(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ])->assertCreated();

        $payload = $this->tablePayload();
        $payload['length_mm'] = 1300;

        $this->postJson('/api/v1/production-orders/confirm', $payload, [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ])->assertStatus(409);

        $this->assertDatabaseCount('production_orders', 1);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '11.534000',
        ]);
        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '46.000000',
        ]);
    }

    public function test_insufficient_stock_causes_no_changes(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $oak->update(['quantity_on_hand' => '0.010000']);

        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('production_orders', 0);
        $this->assertDatabaseCount('production_consumptions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '0.010000',
        ]);
        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '50.000000',
        ]);
    }

    public function test_invalid_dimensions_are_rejected(): void
    {
        $this->seedCatalogue();

        $payload = $this->tablePayload();
        $payload['length_mm'] = 500;

        $this->postJson('/api/v1/production-orders/confirm', $payload, [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('production_orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_incompatible_material_is_rejected(): void
    {
        $this->seedCatalogue();

        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $payload = $this->tablePayload();
        $payload['parts'] = [
            ['code' => 'top', 'material_id' => $pine->id],
            ['code' => 'legs', 'material_id' => $pine->id],
        ];

        $this->postJson('/api/v1/production-orders/confirm', $payload, [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('production_orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_bedside_confirmation_creates_consumptions_and_movements(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $birch = Material::where('sku', 'BIR-040')->firstOrFail();
        $foot = Material::where('sku', 'PIN-FOOT')->firstOrFail();
        $brass = Material::where('sku', 'BRA-HDL')->firstOrFail();

        $response = $this->postJson('/api/v1/production-orders/confirm', $this->bedsidePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ]);

        $response->assertCreated()
            ->assertJsonCount(4, 'production_consumptions')
            ->assertJsonCount(4, 'inventory_movements');

        $orderId = $response->json('id');

        $this->assertDatabaseCount('production_consumptions', 4);
        $this->assertDatabaseCount('inventory_movements', 4);

        foreach ([$oak->id, $birch->id, $foot->id, $brass->id] as $materialId) {
            $this->assertDatabaseHas('inventory_movements', [
                'production_order_id' => $orderId,
                'material_id' => $materialId,
                'movement_type' => 'production',
            ]);
        }
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $this->seedCatalogue();

        $this->postJson('/api/v1/production-orders/confirm', $this->tablePayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency-Key header is required.');

        $this->assertDatabaseCount('production_orders', 0);
    }

    public function test_bedside_confirmation_records_one_consumption_per_part(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $birch = Material::where('sku', 'BIR-040')->firstOrFail();
        $foot = Material::where('sku', 'PIN-FOOT')->firstOrFail();
        $brass = Material::where('sku', 'BRA-HDL')->firstOrFail();

        $response = $this->postJson('/api/v1/production-orders/confirm', $this->bedsidePayload(), [
            'Idempotency-Key' => self::IDEMPOTENCY_KEY,
        ]);

        $response->assertCreated();

        $orderId = $response->json('id');

        $this->assertDatabaseCount('production_consumptions', 4);

        $this->assertDatabaseHas('production_consumptions', [
            'production_order_id' => $orderId,
            'model_part_id' => 3,
            'material_id' => $birch->id,
        ]);

        $this->assertDatabaseHas('production_consumptions', [
            'production_order_id' => $orderId,
            'model_part_id' => 4,
            'material_id' => $oak->id,
        ]);

        $this->assertDatabaseHas('production_consumptions', [
            'production_order_id' => $orderId,
            'model_part_id' => 5,
            'material_id' => $brass->id,
        ]);

        $this->assertDatabaseHas('production_consumptions', [
            'production_order_id' => $orderId,
            'model_part_id' => 6,
            'material_id' => $foot->id,
        ]);
    }
}

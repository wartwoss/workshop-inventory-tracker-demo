<?php

namespace Tests\Feature;

use App\Models\FurnitureModel;
use App\Models\Material;
use App\Models\ModelPart;
use Database\Seeders\FurnitureModelSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ModelPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionQuoteApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): void
    {
        $this->seed([
            MaterialSeeder::class,
            FurnitureModelSeeder::class,
            ModelPartSeeder::class,
        ]);
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

    public function test_valid_table_quote_returns_consumption_and_availability(): void
    {
        $this->seedCatalogue();

        $response = $this->postJson('/api/v1/production/quote', $this->tablePayload());

        $response->assertOk()
            ->assertJsonPath('furniture_model.slug', 'table')
            ->assertJsonPath('configuration.quantity', 1)
            ->assertJsonPath('is_sufficient', true);

        $components = $response->json('components');
        $this->assertCount(2, $components);

        $top = collect($components)->firstWhere('part_code', 'top');
        $this->assertSame('m2', $top['unit']);
        $this->assertSame('0.966000', $top['total_quantity']);

        $legs = collect($components)->firstWhere('part_code', 'legs');
        $this->assertSame('ea', $legs['unit']);
        $this->assertSame('4.000000', $legs['total_quantity']);
    }

    public function test_bedside_quote_returns_components_for_each_part(): void
    {
        $this->seedCatalogue();

        $response = $this->postJson('/api/v1/production/quote', $this->bedsidePayload());

        $response->assertOk()
            ->assertJsonPath('is_sufficient', true);

        $components = $response->json('components');
        $this->assertCount(4, $components);

        $this->assertNotNull(collect($components)->firstWhere('part_code', 'carcass'));
        $this->assertNotNull(collect($components)->firstWhere('part_code', 'door'));
        $this->assertNotNull(collect($components)->firstWhere('part_code', 'handle'));
        $this->assertNotNull(collect($components)->firstWhere('part_code', 'feet'));
    }

    public function test_quoting_does_not_change_inventory(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '12.500000',
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '50.000000',
        ]);

        $this->postJson('/api/v1/production/quote', $this->tablePayload(10))->assertOk();

        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '12.500000',
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $pine->id,
            'quantity_on_hand' => '50.000000',
        ]);

        $this->assertDatabaseCount('production_orders', 0);
        $this->assertDatabaseCount('production_consumptions', 0);
    }

    public function test_quote_with_invalid_dimensions_returns_422(): void
    {
        $this->seedCatalogue();

        $payload = $this->tablePayload();
        $payload['length_mm'] = 500; // below minimum 800

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Dimension 'length' must be between 800 and 2400 mm.");
    }

    public function test_quote_with_missing_part_selection_returns_422(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();

        $payload = $this->tablePayload();
        $payload['parts'] = [
            ['code' => 'top', 'material_id' => $oak->id],
            // legs missing
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Missing material selection for part 'legs'.");
    }

    public function test_quote_with_incompatible_unit_returns_422(): void
    {
        $this->seedCatalogue();

        // Pine legs are ea, top expects m2
        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $payload = $this->tablePayload();
        $payload['parts'] = [
            ['code' => 'top', 'material_id' => $pine->id],
            ['code' => 'legs', 'material_id' => $pine->id],
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Unit mismatch for part 'top': expected m2, got ea.");
    }

    public function test_unknown_formula_key_returns_422(): void
    {
        $model = FurnitureModel::factory()->create([
            'slug' => 'unknown-formula',
            'name' => 'Unknown formula model',
            'is_active' => true,
            'min_length_mm' => 100,
            'max_length_mm' => 1000,
            'min_width_mm' => 100,
            'max_width_mm' => 1000,
            'min_height_mm' => 100,
            'max_height_mm' => 1000,
        ]);

        $material = Material::factory()->create([
            'name' => 'Generic wood',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'is_active' => true,
        ]);

        ModelPart::factory()->create([
            'furniture_model_id' => $model->id,
            'code' => 'top',
            'name' => 'Top',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'nonexistent_formula',
            'formula_parameters' => [],
            'is_configurable' => true,
        ]);

        $payload = [
            'furniture_model_slug' => 'unknown-formula',
            'length_mm' => 500,
            'width_mm' => 500,
            'height_mm' => 500,
            'quantity' => 1,
            'parts' => [
                ['code' => 'top', 'material_id' => $material->id],
            ],
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Unsupported formula key: nonexistent_formula');
    }

    public function test_insufficient_stock_marks_quote_insufficient(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $oak->update(['quantity_on_hand' => '0.010000']);

        $response = $this->postJson('/api/v1/production/quote', $this->tablePayload(100));

        $response->assertOk()
            ->assertJsonPath('is_sufficient', false);

        $oakAggregate = collect($response->json('materials'))
            ->firstWhere('material_id', $oak->id);

        $this->assertFalse($oakAggregate['is_sufficient']);
        $this->assertTrue(bccomp($oakAggregate['required_quantity'], $oakAggregate['available_quantity'], 6) > 0);

        // Ensure no stock was deducted
        $this->assertDatabaseHas('materials', [
            'id' => $oak->id,
            'quantity_on_hand' => '0.010000',
        ]);
    }

    public function test_inactive_material_is_rejected(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $oak->update(['is_active' => false]);

        $pine = Material::where('sku', 'PIN-LEG-50')->firstOrFail();

        $payload = $this->tablePayload();
        $payload['parts'] = [
            ['code' => 'top', 'material_id' => $oak->id],
            ['code' => 'legs', 'material_id' => $pine->id],
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Selected material for part 'top' is not available.");
    }

    public function test_bedside_carcass_and_door_produce_separate_consumptions(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $birch = Material::where('sku', 'BIR-040')->firstOrFail();
        $foot = Material::where('sku', 'PIN-FOOT')->firstOrFail();
        $brass = Material::where('sku', 'BRA-HDL')->firstOrFail();

        $payload = $this->bedsidePayload();
        $payload['parts'] = [
            ['code' => 'carcass', 'material_id' => $birch->id],
            ['code' => 'door', 'material_id' => $oak->id],
            ['code' => 'handle', 'material_id' => $brass->id],
            ['code' => 'feet', 'material_id' => $foot->id],
        ];

        $response = $this->postJson('/api/v1/production/quote', $payload);

        $response->assertOk();

        $components = $response->json('components');

        $carcass = collect($components)->firstWhere('part_code', 'carcass');
        $door = collect($components)->firstWhere('part_code', 'door');

        $this->assertSame('m2', $carcass['unit']);
        $this->assertSame('m2', $door['unit']);
        $this->assertNotSame($carcass['material_id'], $door['material_id']);
    }

    public function test_bedside_carcass_consumption_scales_with_dimensions(): void
    {
        $this->seedCatalogue();

        $birch = Material::where('sku', 'BIR-040')->firstOrFail();
        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $foot = Material::where('sku', 'PIN-FOOT')->firstOrFail();
        $brass = Material::where('sku', 'BRA-HDL')->firstOrFail();

        $basePayload = [
            'furniture_model_slug' => 'bedside-cabinet',
            'length_mm' => 450,
            'width_mm' => 400,
            'height_mm' => 650,
            'quantity' => 1,
            'parts' => [
                ['code' => 'carcass', 'material_id' => $birch->id],
                ['code' => 'door', 'material_id' => $oak->id],
                ['code' => 'handle', 'material_id' => $brass->id],
                ['code' => 'feet', 'material_id' => $foot->id],
            ],
        ];

        $baseCarcass = collect($this->postJson('/api/v1/production/quote', $basePayload)->json('components'))
            ->firstWhere('part_code', 'carcass');

        $tallerPayload = $basePayload;
        $tallerPayload['height_mm'] = 800;

        $tallerCarcass = collect($this->postJson('/api/v1/production/quote', $tallerPayload)->json('components'))
            ->firstWhere('part_code', 'carcass');

        $this->assertGreaterThan(
            (float) $baseCarcass['total_quantity'],
            (float) $tallerCarcass['total_quantity']
        );
    }

    public function test_quote_rejects_incompatible_purpose(): void
    {
        $this->seedCatalogue();

        $oak = Material::where('sku', 'OAK-020')->firstOrFail();
        $woodHandle = Material::factory()->create([
            'name' => 'Wood handle',
            'material_group' => 'wood',
            'purpose' => 'handle',
            'stock_unit' => 'ea',
            'quantity_on_hand' => '100.000000',
            'is_active' => true,
        ]);

        // Wood handle shares the wood group with legs but has the wrong purpose.
        $payload = $this->tablePayload();
        $payload['parts'] = [
            ['code' => 'top', 'material_id' => $oak->id],
            ['code' => 'legs', 'material_id' => $woodHandle->id],
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Material purpose 'handle' is not compatible with part 'legs'.");
    }

    public function test_quote_accepts_compatible_purpose_with_matching_unit(): void
    {
        $model = FurnitureModel::factory()->create([
            'slug' => 'shelf-unit',
            'name' => 'Shelf unit',
            'is_active' => true,
            'min_length_mm' => 100,
            'max_length_mm' => 1000,
            'min_width_mm' => 100,
            'max_width_mm' => 1000,
            'min_height_mm' => 100,
            'max_height_mm' => 1000,
        ]);

        $boardM2 = Material::factory()->create([
            'name' => 'Birch board m2',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'quantity_on_hand' => '10.000000',
            'is_active' => true,
        ]);

        ModelPart::factory()->create([
            'furniture_model_id' => $model->id,
            'code' => 'carcass',
            'name' => 'Carcass',
            'component_type' => 'board',
            'material_group' => 'wood',
            'compatible_purposes' => ['board'],
            'consumption_unit' => 'm2',
            'formula_key' => 'cabinet_carcass_m2',
            'formula_parameters' => [
                'waste_factor' => 1.0,
            ],
            'is_configurable' => true,
        ]);

        $payload = [
            'furniture_model_slug' => 'shelf-unit',
            'length_mm' => 500,
            'width_mm' => 400,
            'height_mm' => 300,
            'quantity' => 1,
            'parts' => [
                ['code' => 'carcass', 'material_id' => $boardM2->id],
            ],
        ];

        $this->postJson('/api/v1/production/quote', $payload)
            ->assertOk()
            ->assertJsonPath('is_sufficient', true)
            ->assertJsonPath('components.0.part_code', 'carcass');
    }
    public function test_bookshelf_quote_counts_all_repeated_board_panels(): void
    {
        $this->seedCatalogue();
        $oak = Material::where('sku', 'OAK-020')->firstOrFail();

        $response = $this->postJson('/api/v1/production/quote', [
            'furniture_model_slug' => 'bookshelf',
            'length_mm' => 800,
            'width_mm' => 300,
            'height_mm' => 1600,
            'quantity' => 1,
            'parts' => [
                ['code' => 'sides', 'material_id' => $oak->id],
                ['code' => 'topBottom', 'material_id' => $oak->id],
                ['code' => 'shelves', 'material_id' => $oak->id],
                ['code' => 'back', 'material_id' => $oak->id],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('furniture_model.slug', 'bookshelf')
            ->assertJsonPath('is_sufficient', true);

        $components = collect($response->json('components'))->keyBy('part_code');
        $this->assertSame('1.056000', $components['sides']['total_quantity']);
        $this->assertSame('0.504240', $components['topBottom']['total_quantity']);
        $this->assertSame('0.726106', $components['shelves']['total_quantity']);
        $this->assertSame('1.408000', $components['back']['total_quantity']);
    }

}

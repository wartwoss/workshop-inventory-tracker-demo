<?php

namespace Tests\Feature;

use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaterialApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_can_list_materials(): void
    {
        Material::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/materials');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_material_with_opening_stock(): void
    {
        $payload = [
            'name' => 'Oak Wood',
            'sku' => 'OAK-001',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'opening_quantity' => 12.5,
            'reorder_level' => 5.0,
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertCreated()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 12.5)
            ->assertJsonPath('inventory_movements.0.movement_type', 'opening')
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == 12.5);

        $this->assertDatabaseHas('materials', [
            'name' => 'Oak Wood',
            'sku' => 'OAK-001',
            'quantity_on_hand' => '12.500000',
        ]);
    }

    public function test_creating_material_rejects_invalid_unit(): void
    {
        $payload = [
            'name' => 'Invalid Unit Material',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'litres',
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_unit']);
    }

    public function test_each_material_rejects_fractional_opening_and_reorder_quantities(): void
    {
        $response = $this->postJson('/api/v1/materials', [
            'name' => 'Cabinet Handle',
            'material_group' => 'hardware',
            'purpose' => 'handle',
            'stock_unit' => 'ea',
            'opening_quantity' => 2.5,
            'reorder_level' => 1.5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_quantity', 'reorder_level']);
    }

    public function test_creating_material_rejects_negative_opening_quantity(): void
    {
        $payload = [
            'name' => 'Bad Material',
            'material_group' => 'wood',
            'purpose' => 'board',
            'stock_unit' => 'm2',
            'opening_quantity' => -5,
        ];

        $response = $this->postJson('/api/v1/materials', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_quantity']);
    }

    public function test_can_view_single_material(): void
    {
        $material = Material::factory()->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk()
            ->assertJsonPath('id', $material->id);
    }

    public function test_can_update_material_details_without_changing_stock(): void
    {
        $material = Material::factory()->create([
            'quantity_on_hand' => '100.000000',
        ]);

        $response = $this->putJson("/api/v1/materials/{$material->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 100.0);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'name' => 'Updated Name',
            'quantity_on_hand' => '100.000000',
        ]);
    }

    public function test_cannot_change_stock_unit_after_movements_exist(): void
    {
        $material = Material::factory()->create([
            'purpose' => 'board',
            'stock_unit' => 'm2',
        ]);
        $material->inventoryMovements()->create([
            'movement_type' => 'opening',
            'quantity_delta' => '10.000000',
            'balance_after' => '10.000000',
            'reason' => 'Opening',
        ]);

        $response = $this->putJson("/api/v1/materials/{$material->id}", [
            'stock_unit' => 'm2',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change stock unit after transactions exist.');
    }

    public function test_can_add_stock(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '10.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => 5,
            'reason' => 'Received shipment',
        ]);

        $response->assertOk()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 15.0)
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == 5.0)
            ->assertJsonPath('inventory_movements.0.balance_after', fn ($value) => $value == 15.0);
    }

    public function test_each_material_rejects_fractional_stock_adjustments(): void
    {
        $material = Material::factory()->create([
            'purpose' => 'leg',
            'stock_unit' => 'ea',
            'quantity_on_hand' => '10.000000',
        ]);

        $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => 2.5,
            'reason' => 'Fractional handles are invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_can_remove_stock(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '10.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => -4,
            'reason' => 'Used for sample',
        ]);

        $response->assertOk()
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 6.0)
            ->assertJsonPath('inventory_movements.0.quantity_delta', fn ($value) => $value == -4.0)
            ->assertJsonPath('inventory_movements.0.balance_after', fn ($value) => $value == 6.0);
    }

    public function test_cannot_remove_more_stock_than_available(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '2.000000']);

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => -5,
            'reason' => 'Attempt overdraw',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient stock for adjustment.')
            ->assertJsonPath('quantity_on_hand', fn ($value) => $value == 2.0);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'quantity_on_hand' => '2.000000',
        ]);
    }

    public function test_adjustment_requires_reason(): void
    {
        $material = Material::factory()->create();

        $response = $this->postJson("/api/v1/materials/{$material->id}/adjust", [
            'quantity' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_archived_materials_are_hidden_from_active_list_but_can_be_included(): void
    {
        $active = Material::factory()->create(['is_active' => true]);
        $archived = Material::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson('/api/v1/materials?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_unused_zero_stock_material_can_be_permanently_deleted(): void
    {
        $material = Material::factory()->create([
            'quantity_on_hand' => '0.000000',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/materials/{$material->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('archived', false);

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_material_with_history_or_stock_is_archived_without_losing_history(): void
    {
        $material = Material::factory()->create([
            'quantity_on_hand' => '4.000000',
            'is_active' => true,
        ]);
        $movement = $material->inventoryMovements()->create([
            'movement_type' => 'manual_adjustment',
            'quantity_delta' => '4.000000',
            'balance_after' => '4.000000',
            'reason' => 'Existing stock',
        ]);

        $this->deleteJson("/api/v1/materials/{$material->id}")
            ->assertOk()
            ->assertJsonPath('deleted', false)
            ->assertJsonPath('archived', true);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'is_active' => false,
            'quantity_on_hand' => '4.000000',
        ]);
        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
    }

    public function test_can_view_material_movement_history(): void
    {
        $material = Material::factory()->create(['quantity_on_hand' => '5.000000']);
        $material->inventoryMovements()->create([
            'movement_type' => 'manual_adjustment',
            'quantity_delta' => '5.000000',
            'balance_after' => '5.000000',
            'reason' => 'Initial top-up',
        ]);

        $response = $this->getJson("/api/v1/materials/{$material->id}/movements");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.movement_type', 'manual_adjustment');
    }

  public function test_movement_history_is_immutable(): void
  {
    $material = Material::factory()->create(['quantity_on_hand' => '5.000000']);
    $movement = $material->inventoryMovements()->create([
      'movement_type' => 'manual_adjustment',
      'quantity_delta' => '5.000000',
      'balance_after' => '5.000000',
      'reason' => 'Initial top-up',
    ]);

    // No update/delete routes exist, so HTTP methods other than GET should 404.
    $this->putJson("/api/v1/materials/{$material->id}/movements/{$movement->id}", [
      'quantity_delta' => 999,
    ])->assertNotFound();

    $this->deleteJson("/api/v1/materials/{$material->id}/movements/{$movement->id}")
      ->assertNotFound();
  }

  public function test_can_create_material_with_color_and_texture(): void
  {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('oak.jpg', 200, 200);

    $response = $this->call('POST', '/api/v1/materials', [
      'name' => 'Textured oak',
      'material_group' => 'wood',
      'purpose' => 'board',
      'stock_unit' => 'm2',
      'color_hex' => '#8B5A2B',
    ], [], ['texture' => $file], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertCreated();

    $material = Material::where('name', 'Textured oak')->firstOrFail();
    $this->assertSame('#8B5A2B', $material->color_hex);
    $this->assertNotNull($material->texture_path);
    $this->assertStringStartsWith('textures/', $material->texture_path);
    $this->assertNotNull($material->texture_url);
    Storage::disk('public')->assertExists($material->texture_path);
  }

  public function test_can_update_material_color(): void
  {
    $material = Material::factory()->create(['color_hex' => '#ffffff']);

    $response = $this->putJson("/api/v1/materials/{$material->id}", [
      'color_hex' => '#123456',
    ]);

    $response->assertOk()
      ->assertJsonPath('color_hex', '#123456');

    $this->assertDatabaseHas('materials', [
      'id' => $material->id,
      'color_hex' => '#123456',
    ]);
  }

  public function test_invalid_color_is_rejected(): void
  {
    $response = $this->postJson('/api/v1/materials', [
      'name' => 'Bad color material',
      'material_group' => 'wood',
      'purpose' => 'board',
      'stock_unit' => 'm2',
      'color_hex' => 'not-a-color',
    ]);

    $response->assertUnprocessable()
      ->assertJsonValidationErrors(['color_hex']);
  }

  public function test_invalid_texture_type_is_rejected(): void
  {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->call('POST', '/api/v1/materials', [
      'name' => 'Bad texture material',
      'material_group' => 'wood',
      'purpose' => 'board',
      'stock_unit' => 'm2',
    ], [], ['texture' => $file], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertUnprocessable()
      ->assertJsonValidationErrors(['texture']);

    $this->assertDatabaseMissing('materials', [
      'name' => 'Bad texture material',
    ]);
  }

  public function test_oversized_texture_is_rejected(): void
  {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('huge.jpg', 4000, 4000)->size(6000);

    $response = $this->call('POST', '/api/v1/materials', [
      'name' => 'Huge texture material',
      'material_group' => 'wood',
      'purpose' => 'board',
      'stock_unit' => 'm2',
    ], [], ['texture' => $file], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertUnprocessable()
      ->assertJsonValidationErrors(['texture']);
  }

  public function test_updating_without_texture_preserves_existing_texture(): void
  {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('oak.jpg', 200, 200);
    $material = Material::factory()->create([
      'name' => 'Preserved texture',
      'texture_path' => $file->store('textures', 'public'),
    ]);

    $originalPath = $material->texture_path;

    $this->putJson("/api/v1/materials/{$material->id}", [
      'name' => 'Updated name',
    ])->assertOk();

    $material->refresh();
    $this->assertSame($originalPath, $material->texture_path);
    $this->assertDatabaseHas('materials', [
      'id' => $material->id,
      'texture_path' => $originalPath,
    ]);
    Storage::disk('public')->assertExists($originalPath);
  }

  public function test_can_replace_texture(): void
  {
    Storage::fake('public');

    $oldFile = UploadedFile::fake()->image('old.jpg', 200, 200);
    $material = Material::factory()->create([
      'texture_path' => $oldFile->store('textures', 'public'),
    ]);

    $oldPath = $material->texture_path;

    $newFile = UploadedFile::fake()->image('new.png', 300, 300);

    $response = $this->call('POST', "/api/v1/materials/{$material->id}", [
      '_method' => 'PUT',
    ], [], ['texture' => $newFile], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertOk();

    $material->refresh();
    $this->assertNotNull($material->texture_path);
    $this->assertNotSame($oldPath, $material->texture_path);
    Storage::disk('public')->assertExists($material->texture_path);
    Storage::disk('public')->assertMissing($oldPath);
  }

  public function test_can_remove_texture(): void
  {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('oak.jpg', 200, 200);
    $material = Material::factory()->create([
      'texture_path' => $file->store('textures', 'public'),
    ]);

    $path = $material->texture_path;

    $this->putJson("/api/v1/materials/{$material->id}", [
      'remove_texture' => true,
    ])->assertOk();

    $material->refresh();
    $this->assertNull($material->texture_path);
    Storage::disk('public')->assertMissing($path);
  }

  public function test_can_create_material_with_valid_purpose(): void
  {
    $response = $this->postJson('/api/v1/materials', [
      'name' => 'Board material',
      'material_group' => 'wood',
      'purpose' => 'board',
      'stock_unit' => 'm2',
    ]);

    $response->assertCreated()
      ->assertJsonPath('purpose', 'board');

    $this->assertDatabaseHas('materials', [
      'name' => 'Board material',
      'purpose' => 'board',
    ]);
  }

  public function test_creating_material_rejects_invalid_purpose(): void
  {
    $response = $this->postJson('/api/v1/materials', [
      'name' => 'Bad purpose material',
      'material_group' => 'wood',
      'purpose' => 'spacer',
      'stock_unit' => 'm2',
    ]);

    $response->assertUnprocessable()
      ->assertJsonValidationErrors(['purpose']);
  }

  public function test_can_update_material_purpose(): void
  {
    $material = Material::factory()->create([
      'purpose' => 'leg',
      'stock_unit' => 'ea',
    ]);

    $response = $this->putJson("/api/v1/materials/{$material->id}", [
      'purpose' => 'handle',
    ]);

    $response->assertOk()
      ->assertJsonPath('purpose', 'handle');

    $this->assertDatabaseHas('materials', [
      'id' => $material->id,
      'purpose' => 'handle',
    ]);
  }

  public function test_can_create_other_material_with_sensible_units(): void
  {
    foreach (['ea', 'kg', 'g', 'L', 'mL', 'm'] as $unit) {
      $response = $this->postJson('/api/v1/materials', [
        'name' => "Supply {$unit}",
        'material_group' => 'hardware',
        'purpose' => 'other',
        'stock_unit' => $unit,
      ]);

      $response->assertCreated()
        ->assertJsonPath('purpose', 'other')
        ->assertJsonPath('stock_unit', $unit);
    }
  }

  public function test_other_material_is_excluded_from_furniture_selectors(): void
  {
    $this->seed([
      \Database\Seeders\MaterialSeeder::class,
      \Database\Seeders\FurnitureModelSeeder::class,
      \Database\Seeders\ModelPartSeeder::class,
    ]);

    Material::factory()->create([
      'name' => 'Wood screws',
      'material_group' => 'wood',
      'purpose' => 'other',
      'stock_unit' => 'ea',
      'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/furniture-models/table');

    $response->assertOk();

    foreach ($response->json('parts') as $part) {
      foreach ($part['allowed_materials'] as $material) {
        $this->assertNotSame('other', $material['purpose']);
      }
    }
  }
}

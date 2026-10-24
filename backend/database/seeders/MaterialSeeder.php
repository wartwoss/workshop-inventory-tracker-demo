<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Seed realistic starter materials for local development and demos.
     *
     * Only the three supported purposes are represented here. Non-component
     * materials have been removed from the demo set; they remain deactivated
     * in existing databases through the purpose-simplification migration.
     */
    public function run(): void
    {
        $materials = [
            [
                'name' => 'Oak board 20 mm',
                'sku' => 'OAK-020',
                'material_group' => 'wood',
                'purpose' => 'board',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '12.500000',
                'reorder_level' => '10.000000',
                'visual_key' => 'oak-natural',
                'color_hex' => '#c49a6c',
                'is_active' => true,
            ],
            [
                'name' => 'Walnut board 20 mm',
                'sku' => 'WAL-020',
                'material_group' => 'wood',
                'purpose' => 'board',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '8.000000',
                'reorder_level' => '5.000000',
                'visual_key' => 'walnut',
                'color_hex' => '#5c4033',
                'is_active' => true,
            ],
            [
                'name' => 'Birch board 40 mm',
                'sku' => 'BIR-040',
                'material_group' => 'wood',
                'purpose' => 'board',
                'stock_unit' => 'm2',
                'quantity_on_hand' => '3.000000',
                'reorder_level' => '1.000000',
                'visual_key' => 'birch',
                'color_hex' => '#d4b483',
                'is_active' => true,
            ],
            [
                'name' => 'Pine legs 50×50 mm',
                'sku' => 'PIN-LEG-50',
                'material_group' => 'wood',
                'purpose' => 'leg',
                'stock_unit' => 'ea',
                'quantity_on_hand' => '50.000000',
                'reorder_level' => '20.000000',
                'visual_key' => 'pine',
                'color_hex' => '#e8d4a8',
                'is_active' => true,
            ],
            [
                'name' => 'Pine foot blank',
                'sku' => 'PIN-FOOT',
                'material_group' => 'wood',
                'purpose' => 'leg',
                'stock_unit' => 'ea',
                'quantity_on_hand' => '20.000000',
                'reorder_level' => '8.000000',
                'visual_key' => 'pine',
                'color_hex' => '#e8d4a8',
                'is_active' => true,
            ],
            [
                'name' => 'Brass handle',
                'sku' => 'BRA-HDL',
                'material_group' => 'metal',
                'purpose' => 'handle',
                'stock_unit' => 'ea',
                'quantity_on_hand' => '24.000000',
                'reorder_level' => '20.000000',
                'visual_key' => 'brass',
                'color_hex' => '#b5a642',
                'is_active' => true,
            ],
        ];

        foreach ($materials as $material) {
            Material::factory()->create($material);
        }
    }
}

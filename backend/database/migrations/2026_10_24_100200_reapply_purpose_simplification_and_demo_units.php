<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-apply purpose simplification, component-type mapping, and the demo
     * material unit reset after the original simplification migration was
     * partially rolled back during development.
     */
    public function up(): void
    {
        // Map legacy purposes to the new canonical set.
        DB::table('materials')
            ->whereIn('purpose', ['foot'])
            ->update(['purpose' => Material::PURPOSE_LEG]);

        DB::table('materials')
            ->where('purpose', 'general')
            ->whereIn('stock_unit', ['m2', 'm3'])
            ->update(['purpose' => Material::PURPOSE_BOARD]);

        DB::table('materials')
            ->where('purpose', 'general')
            ->where('stock_unit', 'ea')
            ->update(['purpose' => Material::PURPOSE_HANDLE]);

        DB::table('materials')
            ->where('purpose', 'general')
            ->update([
                'purpose' => Material::PURPOSE_BOARD,
                'is_active' => false,
            ]);

        DB::table('materials')
            ->where('purpose', Material::PURPOSE_BOARD)
            ->whereNotIn('stock_unit', ['m2', 'm3'])
            ->update(['is_active' => false]);

        DB::table('materials')
            ->where('purpose', Material::PURPOSE_LEG)
            ->where('stock_unit', '!=', 'ea')
            ->update(['is_active' => false]);

        DB::table('materials')
            ->where('purpose', Material::PURPOSE_HANDLE)
            ->where('stock_unit', '!=', 'ea')
            ->update(['is_active' => false]);

        $componentMap = [
            'carcass' => Material::PURPOSE_BOARD,
            'door' => Material::PURPOSE_BOARD,
            'top' => Material::PURPOSE_BOARD,
            'shelf' => Material::PURPOSE_BOARD,
            'legs' => Material::PURPOSE_LEG,
            'feet' => Material::PURPOSE_LEG,
            'handle' => Material::PURPOSE_HANDLE,
        ];

        foreach ($componentMap as $code => $type) {
            DB::table('model_parts')
                ->where('code', $code)
                ->update([
                    'component_type' => $type,
                    'compatible_purposes' => json_encode([$type]),
                ]);
        }

        DB::table('model_parts')
            ->whereNull('component_type')
            ->update(['component_type' => Material::PURPOSE_BOARD]);

        DB::table('model_parts')
            ->whereIn('component_type', [Material::PURPOSE_LEG])
            ->update([
                'consumption_unit' => 'ea',
                'formula_key' => 'fixed_each',
                'formula_parameters' => json_encode(['count_per_unit' => 1]),
            ]);

        DB::table('materials')
            ->where('sku', 'PIN-LEG-50')
            ->update([
                'purpose' => Material::PURPOSE_LEG,
                'stock_unit' => 'ea',
                'quantity_on_hand' => '50.000000',
                'reorder_level' => '20.000000',
            ]);

        DB::table('materials')
            ->where('sku', 'PIN-FOOT')
            ->update([
                'purpose' => Material::PURPOSE_LEG,
                'stock_unit' => 'ea',
                'quantity_on_hand' => '20.000000',
                'reorder_level' => '8.000000',
            ]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // No safe reversal for the demo unit reset; leave data as-is.
    }
};

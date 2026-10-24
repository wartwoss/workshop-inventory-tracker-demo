<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Simplify material purposes to the three supported values and map existing
     * furniture parts to component types.
     *
     * Unsupported active materials are deactivated so historical stock and
     * movement records are preserved. They can be edited later into a supported
     * purpose/unit combination before reactivation.
     */
    public function up(): void
    {
        // Map legacy purposes to the new canonical set.
        DB::table('materials')
            ->whereIn('purpose', ['foot'])
            ->update(['purpose' => Material::PURPOSE_LEG]);

        // Formerly "general" area materials become board materials.
        DB::table('materials')
            ->where('purpose', 'general')
            ->whereIn('stock_unit', ['m2', 'm3'])
            ->update(['purpose' => Material::PURPOSE_BOARD]);

        // Formerly "general" piece materials become handle materials.
        DB::table('materials')
            ->where('purpose', 'general')
            ->where('stock_unit', 'ea')
            ->update(['purpose' => Material::PURPOSE_HANDLE]);

        // Any remaining general material (e.g. kg) is set to board and deactivated.
        DB::table('materials')
            ->where('purpose', 'general')
            ->update([
                'purpose' => Material::PURPOSE_BOARD,
                'is_active' => false,
            ]);

        // Deactivate materials whose unit is not compatible with their new purpose.
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

        // Map existing model-part codes to component types and compatibility.
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

        // Any remaining part without a component type keeps its compatibility list
        // but is marked as a board component by default.
        DB::table('model_parts')
            ->whereNull('component_type')
            ->update(['component_type' => Material::PURPOSE_BOARD]);

        // Convert leg and foot parts to piece-count consumption. Historical
        // consumption records keep their original unit; future quotes use ea.
        DB::table('model_parts')
            ->whereIn('component_type', [Material::PURPOSE_LEG])
            ->update([
                'consumption_unit' => 'ea',
                'formula_key' => 'fixed_each',
                'formula_parameters' => json_encode(['count_per_unit' => 1]),
            ]);

        // Convert demo leg/foot materials from volume to piece counts.
        // The old balances were demo values; this is a deliberate manual reset
        // documented in the migration and README.
        DB::table('materials')
            ->where('sku', 'PIN-LEG-50')
            ->update([
                'stock_unit' => 'ea',
                'quantity_on_hand' => '50.000000',
                'reorder_level' => '20.000000',
            ]);

        DB::table('materials')
            ->where('sku', 'PIN-FOOT')
            ->update([
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
        DB::table('model_parts')->update([
            'component_type' => null,
            'compatible_purposes' => null,
        ]);

        DB::table('materials')->update([
            'purpose' => null,
            'is_active' => true,
        ]);
    }
};

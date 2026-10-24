<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove m3 as a board unit.
     *
     * - Deactivate existing board materials that still use m3 so they cannot be
     *   selected for new furniture components. Historical stock movements and
     *   production orders referencing these materials are preserved.
     * - Convert legacy volume-based board part formulas to area-based m2 formulas.
     *
     * Any m3 board balance must be reconciled manually: create a new m2 board
     * material with a correct area balance and, if desired, adjust the old
     * material out of stock before reactivating it as a non-board item.
     */
    public function up(): void
    {
        // Deactivate board materials measured in m3. They remain visible in
        // inventory history but cannot be assigned to furniture parts.
        DB::table('materials')
            ->where('purpose', Material::PURPOSE_BOARD)
            ->where('stock_unit', 'm3')
            ->update(['is_active' => false]);

        // Convert legacy cabinet-carcass volume formulas to area formulas.
        DB::table('model_parts')
            ->where('formula_key', 'cabinet_carcass_m3')
            ->update([
                'consumption_unit' => 'm2',
                'formula_key' => 'cabinet_carcass_m2',
            ]);

        // Remove thickness from carcass formula parameters; panel area no longer
        // depends on thickness for stock consumption.
        DB::table('model_parts')
            ->where('formula_key', 'cabinet_carcass_m2')
            ->update([
                'formula_parameters' => DB::raw("JSON_REMOVE(formula_parameters, '$.panel_thickness_mm')"),
            ]);

        // Convert any remaining custom board parts that referenced m3 to m2.
        // The exact quantity must be recalculated from the component dimensions
        // by the quote service; this only fixes the unit label.
        DB::table('model_parts')
            ->where('component_type', Material::PURPOSE_BOARD)
            ->where('consumption_unit', 'm3')
            ->update([
                'consumption_unit' => 'm2',
                'formula_key' => 'builder_board_area_m2',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversal is not safe because it would re-allow m3 board units.
    }
};

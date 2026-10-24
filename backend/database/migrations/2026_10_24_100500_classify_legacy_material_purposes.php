<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Classify existing materials with no purpose so they remain useful in the
     * inventory and custom model builder. Only metadata changes; stock balances,
     * movements, and production history are left untouched.
     */
    public function up(): void
    {
        // Wood panels tracked by face area are boards.
        DB::table('materials')
            ->whereNull('purpose')
            ->where('material_group', 'wood')
            ->where('stock_unit', 'm2')
            ->update(['purpose' => Material::PURPOSE_BOARD]);

        // Recognizable individual handles can safely be mapped by their name.
        DB::table('materials')
            ->whereNull('purpose')
            ->where('stock_unit', 'ea')
            ->whereRaw('LOWER(name) LIKE ?', ['%handle%'])
            ->update(['purpose' => Material::PURPOSE_HANDLE]);

        // Recognizable individual legs/feet can safely be mapped by their name.
        DB::table('materials')
            ->whereNull('purpose')
            ->where('stock_unit', 'ea')
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%leg%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%foot%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%feet%']);
            })
            ->update(['purpose' => Material::PURPOSE_LEG]);

        // Unclassified supplies and non-panel materials remain trackable but
        // are not offered for model components.
        DB::table('materials')
            ->whereNull('purpose')
            ->update(['purpose' => Material::PURPOSE_OTHER]);

        // Ensure legacy active materials with a purpose/unit mismatch cannot be
        // selected for new production; never alter their quantity or history.
        DB::table('materials')
            ->where('purpose', Material::PURPOSE_BOARD)
            ->where('stock_unit', '!=', 'm2')
            ->update(['is_active' => false]);

        DB::table('materials')
            ->where('purpose', Material::PURPOSE_LEG)
            ->where('stock_unit', '!=', 'ea')
            ->update(['is_active' => false]);

        DB::table('materials')
            ->where('purpose', Material::PURPOSE_HANDLE)
            ->where('stock_unit', '!=', 'ea')
            ->update(['is_active' => false]);

        // m3 is no longer a supported inventory unit. Keep legacy balances
        // visible for history/reconciliation but never offer them in active use.
        DB::table('materials')
            ->where('stock_unit', 'm3')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Do not remove purposes: doing so would make existing materials
        // unavailable to the builder and would undo metadata reconciliation.
    }
};

<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill material purposes and furniture-part compatibility.
     *
     * Existing local materials are left alone unless they match a known demo SKU.
     * Demo records are updated to the three supported purposes.
     */
    public function up(): void
    {
        $purposeBySku = [
            'OAK-020' => Material::PURPOSE_BOARD,
            'WAL-020' => Material::PURPOSE_BOARD,
            'PIN-LEG-50' => Material::PURPOSE_LEG,
            'BIR-040' => Material::PURPOSE_BOARD,
            'PIN-FOOT' => Material::PURPOSE_LEG,
            'BRA-HDL' => Material::PURPOSE_HANDLE,
            'CER-WHT' => Material::PURPOSE_BOARD,
            'UPH-GRY' => Material::PURPOSE_BOARD,
            'STL-BRK' => Material::PURPOSE_HANDLE,
            'GLU-WD' => Material::PURPOSE_BOARD,
        ];

        foreach ($purposeBySku as $sku => $purpose) {
            DB::table('materials')
                ->where('sku', $sku)
                ->update(['purpose' => $purpose]);
        }

        $compatibilityByCode = [
            'carcass' => [Material::PURPOSE_BOARD],
            'door' => [Material::PURPOSE_BOARD],
            'top' => [Material::PURPOSE_BOARD],
            'shelf' => [Material::PURPOSE_BOARD],
            'legs' => [Material::PURPOSE_LEG],
            'handle' => [Material::PURPOSE_HANDLE],
            'feet' => [Material::PURPOSE_LEG],
        ];

        foreach ($compatibilityByCode as $code => $purposes) {
            DB::table('model_parts')
                ->where('code', $code)
                ->update(['compatible_purposes' => json_encode($purposes)]);
        }

        DB::table('model_parts')
            ->whereNull('compatible_purposes')
            ->update(['compatible_purposes' => json_encode([Material::PURPOSE_BOARD])]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('materials')->update(['purpose' => null]);
        DB::table('model_parts')->update(['compatible_purposes' => null]);
    }
};
